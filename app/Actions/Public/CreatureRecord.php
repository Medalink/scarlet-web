<?php

namespace App\Actions\Public;

use App\Actions\Rollups\DropRates;
use App\Actions\Rollups\KillLoot;
use App\Actions\Rollups\SoloLoot;
use App\Actions\Rollups\SourceDrops;
use App\Actions\Rollups\SourceRuleArms;
use App\Actions\Rollups\WorldHeat;
use App\Support\ArmSplit;
use App\Support\KillerKind;
use App\Support\LootRead;
use App\Support\NameKind;
use App\Support\Rollup;
use App\Support\RollupRead;
use App\Support\Server;
use App\Support\WorldLayer;
use Illuminate\Support\Facades\DB;

class CreatureRecord
{
    /** The source kind of a creature in the drop aggregates. */
    private const string SOURCE = 'c';

    public function __construct(
        private readonly RollupRead $read,
        private readonly ResolveScope $resolveScope,
        private readonly DropRates $drops,
        private readonly SourceDrops $dropTable,
        private readonly SourceRuleArms $arms,
        private readonly SoloLoot $solo,
        private readonly KillLoot $looted,
        private readonly WorldHeat $heat,
        private readonly MapZones $zones,
        private readonly CreaturePoints $points,
        private readonly ListRulesets $rulesets,
    ) {}

    /**
     * Everything the creature page and `/public/creatures/{id}` show: what it is, kills and deaths to it, its drops
     * under each loot rule, its drop table over every rule or over `$rule` alone, its skinning table, its solo
     * loot against its solo kills, its drop rate in each loot rule and in each group size under one rule, and the cells
     * where it was met.
     * Null when nothing is known by this id or the ruleset is not known. Numbers under the character threshold are
     * null or missing.
     *
     * @return array<string, mixed>|null
     */
    public function handle(int $id, ?string $ruleset, Server $server, ?string $rule = null): ?array
    {
        $scope = $this->resolveScope->handle($ruleset, $server);
        $name = DB::table('names')->where('kind', NameKind::Npc->value)->where('id', $id)->orderByDesc('votes')->orderBy('name')->value('name');
        if ($scope === null || ! is_string($name)) {
            return null;
        }
        $ids = $scope->ids;
        $table = $this->dropTable->handle(self::SOURCE, $id, $rule);

        return [
            'scope' => $scope,
            'rulesets' => $this->rulesets->handle(),
            'id' => $id,
            'name' => $name,
            'facts' => $this->facts($id, $ids),
            'kills' => $this->kills($id, $ids),
            'deaths' => $this->deaths($name, $ids),
            'deaths_by_level' => $this->deathsByLevel($name, $ids),
            'drops' => $this->drops->handle(self::SOURCE, $id),
            'drop_table' => $table,
            'skin_table' => $this->dropTable->handle(self::SOURCE, $id, null, LootRead::Skin),
            'rule_arms' => $this->arms->handle(self::SOURCE, $id, ArmSplit::Rule),
            'size_arms' => $this->arms->handle(self::SOURCE, $id, ArmSplit::Size, $rule),
            'solo' => $this->solo->handle($id),
            'looted' => $this->looted->handle($id, $table['rule']),
            'heat' => $this->heat->handle(WorldLayer::Creatures, $ids, $id),
            'points' => $this->points->handle($id, $ids),
            'map_zones' => $this->zones->handle($ids),
        ];
    }

    /**
     * @param  int|list<int>|null  $ids
     * @return array<string, mixed>
     */
    private function facts(int $id, int|array|null $ids): array
    {
        $rows = $this->read->rows($this->read->shown(
            $this->read->from(Rollup::CreatureFactsDaily, $ids)
                ->selectRaw('zone_id, (select z.name from zones z where z.id = zone_id) as zone, min(npc_level) as level_min, max(npc_level) as level_max')
                ->selectRaw('max(creature_type) as type, max(classification) as classification, max(family) as family, sum(sightings) as sightings, distinct_count(rollup(characters)) as characters')
                ->where('npc_id', $id)->groupBy('zone_id')->orderByRaw('sum(sightings) desc')->orderBy('zone_id')->limit(5),
        ));
        if ($rows === []) {
            return ['level_min' => null, 'level_max' => null, 'type' => null, 'classification' => null, 'family' => null, 'zones' => []];
        }
        $lows = array_filter(array_column($rows, 'level_min'), fn (mixed $level): bool => $level !== null);
        $highs = array_filter(array_column($rows, 'level_max'), fn (mixed $level): bool => $level !== null);

        return [
            'level_min' => $lows === [] ? null : (int) min($lows),
            'level_max' => $highs === [] ? null : (int) max($highs),
            'type' => $rows[0]['type'], 'classification' => $rows[0]['classification'], 'family' => $rows[0]['family'],
            'zones' => array_map(fn (array $row): array => ['id' => (int) $row['zone_id'], 'name' => $row['zone'], 'sightings' => (int) $row['sightings']], array_filter($rows, fn (array $row): bool => $row['zone_id'] !== null)),
        ];
    }

    /**
     * @param  int|list<int>|null  $ids
     * @return array{kills: int|null, own: int|null, characters: int|null}
     */
    private function kills(int $id, int|array|null $ids): array
    {
        $row = $this->read->rows($this->read->shown(
            $this->read->from(Rollup::KillsDailyRuleset, $ids)
                ->selectRaw('sum(kills) as kills, sum(own) as own, distinct_count(rollup(characters)) as characters')->where('npc_id', $id),
        ))[0] ?? null;

        return ['kills' => $row === null ? null : (int) $row['kills'], 'own' => $row === null ? null : (int) $row['own'], 'characters' => $row === null ? null : (int) $row['characters']];
    }

    /**
     * @param  int|list<int>|null  $ids
     * @return array{deaths: int|null, characters: int|null}
     */
    private function deaths(string $name, int|array|null $ids): array
    {
        $row = $this->read->rows($this->read->shown(
            $this->read->from(Rollup::DeathsDailyRuleset, $ids)
                ->selectRaw('sum(deaths) as deaths, distinct_count(rollup(characters)) as characters')
                ->whereRaw('killer_kind is distinct from ?', [KillerKind::Player->value])->where('killer', $name),
        ))[0] ?? null;

        return ['deaths' => $row === null ? null : (int) $row['deaths'], 'characters' => $row === null ? null : (int) $row['characters']];
    }

    /**
     * @param  int|list<int>|null  $ids
     * @return list<array<string, mixed>> level, deaths
     */
    private function deathsByLevel(string $name, int|array|null $ids): array
    {
        return $this->read->rows($this->read->shown(
            $this->read->from(Rollup::DeathsDailyRuleset, $ids)
                ->selectRaw('level, sum(deaths) as deaths, distinct_count(rollup(characters)) as characters')
                ->whereRaw('killer_kind is distinct from ?', [KillerKind::Player->value])->where('killer', $name)->whereNotNull('level')
                ->groupBy('level')->orderBy('level'),
        ));
    }
}
