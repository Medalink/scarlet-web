<?php

namespace App\Actions\Public;

use App\Support\NameKind;
use App\Support\Rollup;
use App\Support\RollupRead;
use App\Support\Server;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class QuestRecord
{
    /** The event of a quest turned in (quest_events.event). */
    public const string TURNED_IN = 'turned_in';

    public function __construct(
        private readonly RollupRead $read,
        private readonly ResolveScope $resolveScope,
        private readonly ListRulesets $rulesets,
    ) {}

    /**
     * Everything the quest page and `/public/quests/{id}` show: accepts, turn-ins and abandons, the experience it
     * pays, the level it is turned in at and where. Null when nothing is known by this id or the ruleset is not
     * known. Numbers under the character threshold are null.
     *
     * @return array<string, mixed>|null
     */
    public function handle(int $id, ?string $ruleset, Server $server): ?array
    {
        $scope = $this->resolveScope->handle($ruleset, $server);
        $name = DB::table('names')->where('kind', NameKind::Quest->value)->where('id', $id)->orderByDesc('votes')->orderBy('name')->value('name');
        if ($scope === null || ! is_string($name)) {
            return null;
        }
        $ids = $scope->ids;

        $totals = $this->read->rows($this->read->shown(
            $this->read->from(Rollup::QuestsDailyRuleset, $ids)
                ->selectRaw('sum(turn_ins) as turn_ins, sum(accepts) as accepts, sum(abandons) as abandons, sum(xp) as xp, distinct_count(rollup(characters)) as characters')
                ->where('quest_id', $id),
        ))[0] ?? null;

        return [
            'scope' => $scope,
            'rulesets' => $this->rulesets->handle(),
            'id' => $id,
            'name' => $name,
            'totals' => $totals,
            'levels' => $totals === null ? [] : $this->levels($id, $scope->ids),
            'zones' => $totals === null ? [] : $this->zones($id, $scope->ids),
        ];
    }

    /**
     * The level characters turn the quest in at.
     *
     * @param  int|list<int>|null  $ids
     * @return list<array{level: int, turn_ins: int}>
     */
    private function levels(int $id, int|array|null $ids): array
    {
        return array_values($this->events($id, $ids)->whereNotNull('level')->groupBy('level')->orderBy('level')
            ->selectRaw('level, count(*) as turn_ins')->get()
            ->map(fn (object $row): array => ['level' => (int) $row->level, 'turn_ins' => (int) $row->turn_ins])->all());
    }

    /**
     * Where the quest is turned in.
     *
     * @param  int|list<int>|null  $ids
     * @return list<array{zone_id: int, zone: string|null, turn_ins: int}>
     */
    private function zones(int $id, int|array|null $ids): array
    {
        return array_values($this->events($id, $ids)->whereNotNull('zone_id')->groupBy('zone_id')->orderByRaw('count(*) desc')->orderBy('zone_id')->limit(5)
            ->selectRaw('zone_id, (select z.name from zones z where z.id = zone_id) as zone, count(*) as turn_ins')->get()
            ->map(fn (object $row): array => ['zone_id' => (int) $row->zone_id, 'zone' => $row->zone, 'turn_ins' => (int) $row->turn_ins])->all());
    }

    /** @param  int|list<int>|null  $ids */
    private function events(int $id, int|array|null $ids): Builder
    {
        return DB::table('quest_events')
            ->where('quest_id', $id)->where('event', self::TURNED_IN)->where('counted', true)
            ->when(is_int($ids), fn ($q) => $q->where('ruleset_id', $ids))
            ->when(is_array($ids), fn ($q) => $q->whereIn('ruleset_id', $ids));
    }
}
