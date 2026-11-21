<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class TimePerLevel
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Played seconds spent at each level the character has left: median and quartiles.
     *
     * @return list<array<string, mixed>> level, characters, q1, median, q3
     */
    public function handle(?int $rulesetId = null): array
    {
        $query = $this->read->from(Rollup::LevelTimeRuleset, $rulesetId)
            ->selectRaw('level, distinct_count(rollup(characters)) as characters, sum(completed_levels) as completed')
            ->selectRaw('approx_percentile(0.25, rollup(spent)) as q1')
            ->selectRaw('approx_percentile(0.5, rollup(spent)) as median')
            ->selectRaw('approx_percentile(0.75, rollup(spent)) as q3')
            ->groupBy('level')
            ->havingRaw('sum(completed_levels) > 0')
            ->orderBy('level');

        return $this->read->rows($this->read->shown($query));
    }
}
