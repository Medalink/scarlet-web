<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class DungeonRuns
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Runs per instance with the time inside as median and quartiles. A run that carries on the run before it
     * is not counted as a run, but its time and bosses are.
     *
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array<string, mixed>> instance_id, name, runs, characters, deaths, bosses, q1, median, q3
     */
    public function handle(int|array|null $rulesetId = null): array
    {
        $query = $this->read->from(Rollup::DungeonsDailyRuleset, $rulesetId)
            ->selectRaw('instance_id, (select z.name from zones z where z.instance_id = dungeons_daily_ruleset.instance_id order by z.id limit 1) as name')
            ->selectRaw('sum(runs) as runs, distinct_count(rollup(characters)) as characters, sum(deaths) as deaths, sum(bosses) as bosses')
            ->selectRaw('approx_percentile(0.25, rollup(inside)) as q1')
            ->selectRaw('approx_percentile(0.5, rollup(inside)) as median')
            ->selectRaw('approx_percentile(0.75, rollup(inside)) as q3')
            ->groupBy('instance_id')
            ->orderByRaw('sum(runs) desc')
            ->orderBy('instance_id');

        return $this->read->rows($this->read->shown($query));
    }
}
