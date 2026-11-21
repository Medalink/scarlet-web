<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class LevellingCurve
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Played seconds before a character reached each level: median and quartiles over the characters that
     * reached it, for one ruleset or all.
     *
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array<string, mixed>> level, characters, p10, q1, median, q3
     */
    public function handle(int|array|null $rulesetId = null): array
    {
        $query = $this->read->from(Rollup::LevelTimeRuleset, $rulesetId)
            ->selectRaw('level, distinct_count(rollup(characters)) as characters')
            ->selectRaw('approx_percentile(0.1, rollup(reach)) as p10')
            ->selectRaw('approx_percentile(0.25, rollup(reach)) as q1')
            ->selectRaw('approx_percentile(0.5, rollup(reach)) as median')
            ->selectRaw('approx_percentile(0.75, rollup(reach)) as q3')
            ->groupBy('level')
            ->orderBy('level');

        return $this->read->rows($this->read->shown($query));
    }
}
