<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class BusiestZones
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The zones where most play time was spent, with the deaths in them. Zone ids are uiMap ids, or the negative
     * instance id of a dungeon.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array<string, mixed>> zone_id, zone, seconds, deaths, characters
     */
    public function handle(int|array|null $rulesetId = null, int $limit = 12): array
    {
        $query = $this->read->from(Rollup::PlayHourlyRuleset, $rulesetId)
            ->selectRaw('zone_id, (select z.name from zones z where z.id = zone_id) as zone, (sum(ms) / 1000.0)::float as seconds, distinct_count(rollup(characters)) as characters')
            ->whereNotNull('zone_id')
            ->groupBy('zone_id')
            ->orderByRaw('sum(ms) desc')
            ->orderBy('zone_id')
            ->limit($limit);
        $rows = $this->read->rows($this->read->shown($query));

        $deaths = $this->read->from(Rollup::DeathsDailyRuleset, $rulesetId)
            ->selectRaw('zone_id, sum(deaths) as deaths')
            ->whereIn('zone_id', array_column($rows, 'zone_id'))
            ->groupBy('zone_id')
            ->pluck('deaths', 'zone_id');

        return array_map(fn (array $row): array => [...$row, 'deaths' => (int) ($deaths[$row['zone_id']] ?? 0)], $rows);
    }
}
