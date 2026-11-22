<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\Rollup;
use App\Support\RollupRead;

class PlayTimeByLevel
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Played seconds at each level, summed over characters.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return list<array<string, mixed>> level, seconds, characters
     */
    public function handle(int|array|CharacterScope|null $scope = null): array
    {
        $query = $this->read->from(Rollup::PlayHourlyRuleset, $scope)
            ->selectRaw('level, (sum(ms) / 1000.0)::float as seconds, distinct_count(rollup(characters)) as characters')
            ->whereNotNull('level')
            ->groupBy('level')
            ->orderBy('level');

        return $this->read->rows($this->read->shown($query, $scope));
    }
}
