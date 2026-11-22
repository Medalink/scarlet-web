<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\Profession;
use App\Support\Rollup;
use App\Support\RollupRead;

class CraftPerPoint
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Crafts and rises in rank by the rank before the craft, in bands of 25, ascending. A craft with no rank on record
     * is left out, and so is a band fewer than the scope's threshold of characters crafted in.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return list<array{band: int, crafts: int, skill_ups: int}>
     */
    public function handle(Profession $profession, int|array|CharacterScope|null $scope = null): array
    {
        $query = $this->read->from(Rollup::CraftsDailyShapeRuleset, $scope)
            ->where('profession_id', $profession->skillLine())
            ->whereNotNull('skill_band')
            ->selectRaw('skill_band as band, sum(crafts) as crafts, sum(skill_ups) as skill_ups')
            ->groupBy('skill_band')
            ->orderBy('skill_band');

        return array_map(
            fn (array $row): array => ['band' => (int) $row['band'], 'crafts' => (int) $row['crafts'], 'skill_ups' => (int) $row['skill_ups']],
            $this->read->rows($this->read->shown($query, $scope)),
        );
    }
}
