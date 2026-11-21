<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class XpSourcesByLevel
{
    public function __construct(private readonly RollupRead $read) {}

    /** @return list<array<string, mixed>> level, source, xp, gains, characters */
    public function handle(?int $rulesetId = null): array
    {
        $query = $this->read->from(Rollup::XpDailyRuleset, $rulesetId)
            ->selectRaw('level, source, sum(xp) as xp, sum(gains) as gains, distinct_count(rollup(characters)) as characters')
            ->groupBy('level', 'source')
            ->orderBy('level')
            ->orderBy('source');

        return $this->read->rows($this->read->shown($query));
    }
}
