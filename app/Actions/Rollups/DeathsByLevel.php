<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class DeathsByLevel
{
    public function __construct(private readonly RollupRead $read) {}

    /** @return list<array<string, mixed>> level, deaths, characters */
    public function handle(?int $rulesetId = null): array
    {
        $query = $this->read->from(Rollup::DeathsDailyRuleset, $rulesetId)
            ->selectRaw('level, sum(deaths) as deaths, distinct_count(rollup(characters)) as characters')
            ->groupBy('level')
            ->orderBy('level');

        return $this->read->rows($this->read->shown($query));
    }
}
