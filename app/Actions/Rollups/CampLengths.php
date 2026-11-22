<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class CampLengths
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Visits by length: `step` is the length in half minutes, at most 40 (20 minutes and over). Only steps with visits
     * and enough characters are listed.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array{step: int, visits: int}>
     */
    public function handle(int|array|null $rulesetId = null): array
    {
        $query = $this->read->from(Rollup::CampStaysHourlyRuleset, $rulesetId)
            ->selectRaw('step, sum(visits) as visits')
            ->groupBy('step')
            ->havingRaw('sum(visits) > 0')
            ->orderBy('step');

        return array_map(
            fn (array $row): array => ['step' => (int) $row['step'], 'visits' => (int) $row['visits']],
            $this->read->rows($this->read->shown($query)),
        );
    }
}
