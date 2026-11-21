<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class PlayTimeByLevel
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Played seconds at each level, summed over characters.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array<string, mixed>> level, seconds, characters
     */
    public function handle(int|array|null $rulesetId = null): array
    {
        $query = $this->read->from(Rollup::PlayHourlyRuleset, $rulesetId)
            ->selectRaw('level, (sum(ms) / 1000.0)::float as seconds, distinct_count(rollup(characters)) as characters')
            ->whereNotNull('level')
            ->groupBy('level')
            ->orderBy('level');

        return $this->read->rows($this->read->shown($query));
    }
}
