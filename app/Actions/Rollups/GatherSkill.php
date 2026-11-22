<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\GatherKind;
use App\Support\Rollup;

class GatherSkill
{
    public function __construct(private readonly RankSummary $ranks) {}

    /**
     * The median over characters of the highest rank each had in the scope, and how many of them reached the cap. A
     * character with no rank on record is left out. Both are null while fewer than the scope's threshold of
     * characters have one.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return array{median_skill: float|null, at_cap: int|null}
     */
    public function handle(GatherKind $kind, int|array|CharacterScope|null $scope = null): array
    {
        return $this->ranks->handle(Rollup::GathersDailyCharacter, 'kind', $kind->value, $scope);
    }
}
