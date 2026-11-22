<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\GatherKind;
use App\Support\Rollup;
use App\Support\RollupRead;

class GatherPerPoint
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Looted attempts and rises in rank by the rank before the attempt, in bands of 25, ascending. An attempt with no
     * rank on record is left out, and so is a band fewer than the scope's threshold of characters gathered in.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return list<array{band: int, looted: int, skill_ups: int}>
     */
    public function handle(GatherKind $kind, int|array|CharacterScope|null $scope = null): array
    {
        $query = $this->read->from(Rollup::GathersDailyShapeRuleset, $scope)
            ->where('kind', $kind->value)
            ->whereNotNull('skill_band')
            ->selectRaw('skill_band as band, sum(looted) as looted, sum(skill_ups) as skill_ups')
            ->groupBy('skill_band')
            ->orderBy('skill_band');

        return array_map(
            fn (array $row): array => ['band' => (int) $row['band'], 'looted' => (int) $row['looted'], 'skill_ups' => (int) $row['skill_ups']],
            $this->read->rows($this->read->shown($query, $scope)),
        );
    }
}
