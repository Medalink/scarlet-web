<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\Rollup;
use App\Support\RollupRead;

class CampByLevel
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Visits and tent rests (a tent held for 30 seconds or more) at each level, ascending. A level fewer than
     * the scope's threshold of characters stayed at is left out.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return list<array{level: int, visits: int, tent_rests: int}>
     */
    public function handle(int|array|CharacterScope|null $scope = null): array
    {
        $query = $this->read->from(Rollup::CampStaysHourlyRuleset, $scope)
            ->selectRaw('level, sum(visits) as visits, sum(tent_rests) as tent_rests')
            ->whereNotNull('level')
            ->groupBy('level')
            ->havingRaw('sum(visits) > 0')
            ->orderBy('level');

        return array_map(
            fn (array $row): array => ['level' => (int) $row['level'], 'visits' => (int) $row['visits'], 'tent_rests' => (int) $row['tent_rests']],
            $this->read->rows($this->read->shown($query, $scope)),
        );
    }
}
