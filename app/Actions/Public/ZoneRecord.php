<?php

namespace App\Actions\Public;

use App\Actions\Rollups\WorldHeat;
use App\Models\MapRect;
use App\Support\DungeonMaps;
use App\Support\KillerKind;
use App\Support\KillerRows;
use App\Support\NameKind;
use App\Support\Rollup;
use App\Support\RollupRead;
use App\Support\Server;
use App\Support\WorldLayer;
use Illuminate\Support\Facades\DB;

class ZoneRecord
{
    public function __construct(
        private readonly RollupRead $read,
        private readonly ResolveScope $resolveScope,
        private readonly WorldHeat $heat,
        private readonly MapZones $zones,
        private readonly ListRulesets $rulesets,
    ) {}

    /**
     * Everything the zone page and `/public/zones/{id}` show: time played here and the levels it is played at,
     * deaths and what caused them, the creatures met here, the zone's place on the world map and a dungeon's floor
     * maps. Zone ids are uiMap ids, or the negative instance id of a dungeon. Null when the zone is unknown or the ruleset is not known.
     *
     * @return array<string, mixed>|null
     */
    public function handle(int $id, ?string $ruleset, Server $server): ?array
    {
        $scope = $this->resolveScope->handle($ruleset, $server);
        $zone = DB::table('zones')->where('id', $id)->first();
        $rect = MapRect::query()->where('ui_map_id', $id)->first();
        if ($scope === null || ($zone === null && $rect === null)) {
            return null;
        }
        $ids = $scope->ids;

        return [
            'scope' => $scope,
            'rulesets' => $this->rulesets->handle(),
            'id' => $id,
            'name' => $zone->name ?? $rect->name ?? 'Zone '.$id,
            'instance_type' => $zone->instance_type ?? null,
            'rect' => $rect === null ? null : ['u0' => $rect->u0, 'v0' => $rect->v0, 'u1' => $rect->u1, 'v1' => $rect->v1],
            'played' => $this->played($id, $ids),
            'deaths' => $this->read->rows($this->read->shown(
                $this->read->from(Rollup::DeathsDailyRuleset, $ids)->selectRaw('sum(deaths) as deaths, distinct_count(rollup(characters)) as characters')->where('zone_id', $id),
            ))[0] ?? null,
            'killers' => $this->killers($id, $ids),
            'creatures' => $this->creatures($id, $ids),
            'heat' => $this->heat->handle(WorldLayer::Paths, $ids, level: 0),
            'death_heat' => $this->heat->handle(WorldLayer::Deaths, $ids),
            'map_zones' => $this->zones->handle($ids),
            'floors' => $this->floors($id),
        ];
    }

    /**
     * A dungeon's floor maps (DungeonMaps), each boss with the id of the creature of that name when one is known:
     * the one met in this dungeon, else the one with the most votes for the name.
     *
     * @return list<array<string, mixed>> name, credit, src, width, height, bosses (name, x, y, npc_id)
     */
    private function floors(int $id): array
    {
        $floors = $id < 0 ? DungeonMaps::floors(-$id) : [];
        $names = array_values(array_unique(array_column(array_merge(...array_column($floors, 'bosses')), 'name')));
        $named = $names === [] ? [] : DB::table('names')->where('kind', NameKind::Npc->value)->whereIn('name', $names)->orderBy('votes')->orderByDesc('id')->get(['id', 'name'])->all();
        $met = $named === [] ? [] : array_column($this->read->rows(
            $this->read->from(Rollup::CreatureFactsDaily, null)->select('npc_id')->where('zone_id', $id)->whereIn('npc_id', array_column($named, 'id'))->groupBy('npc_id'),
        ), 'npc_id');
        $known = [];
        foreach ([$named, array_filter($named, fn (object $row): bool => in_array($row->id, $met))] as $rows) {
            foreach ($rows as $row) {
                $known[$row->name] = (int) $row->id;
            }
        }

        return array_map(fn (array $floor): array => [...$floor, 'bosses' => array_map(
            fn (array $boss): array => [...$boss, 'npc_id' => $known[$boss['name']] ?? null],
            $floor['bosses'],
        )], $floors);
    }

    /**
     * @param  int|list<int>|null  $ids
     * @return array<string, mixed>|null
     */
    private function played(int $id, int|array|null $ids): ?array
    {
        return $this->read->rows($this->read->shown(
            $this->read->from(Rollup::PlayHourlyRuleset, $ids)
                ->selectRaw('(sum(ms) / 1000.0)::float as seconds, distinct_count(rollup(characters)) as characters, min(level) as level_min, max(level) as level_max')
                ->where('zone_id', $id),
        ))[0] ?? null;
    }

    /**
     * @param  int|list<int>|null  $ids
     * @return list<array<string, mixed>> kind, killer, deaths
     */
    private function killers(int $id, int|array|null $ids): array
    {
        return KillerRows::shown($this->read->rows($this->read->shown(
            $this->read->from(Rollup::DeathsDailyRuleset, $ids)
                ->selectRaw('killer_kind as kind, killer, sum(deaths) as deaths, distinct_count(rollup(characters)) as characters')
                ->selectRaw('(select n.id from names n where n.kind = ? and killer_kind is distinct from ? and n.name = killer order by n.votes desc limit 1) as npc_id', [NameKind::Npc->value, KillerKind::Player->value])
                ->where('zone_id', $id)->whereNotNull('killer')->groupBy('killer_kind', 'killer')->orderByRaw('sum(deaths) desc')->orderBy('killer')->limit(8),
        )));
    }

    /**
     * @param  int|list<int>|null  $ids
     * @return list<array<string, mixed>> npc_id, name, sightings
     */
    private function creatures(int $id, int|array|null $ids): array
    {
        return $this->read->rows($this->read->shown(
            $this->read->from(Rollup::CreatureFactsDaily, $ids)
                ->selectRaw("npc_id, (select n.name from names n where n.kind = 'npc' and n.id = npc_id order by n.votes desc, n.name limit 1) as name")
                ->selectRaw('sum(sightings) as sightings, min(npc_level) as level_min, max(npc_level) as level_max, distinct_count(rollup(characters)) as characters')
                ->where('zone_id', $id)->groupBy('npc_id')->orderByRaw('sum(sightings) desc')->orderBy('npc_id')->limit(12),
        ));
    }
}
