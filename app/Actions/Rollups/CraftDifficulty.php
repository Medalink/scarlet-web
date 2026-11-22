<?php

namespace App\Actions\Rollups;

use App\Support\Profession;
use App\Support\Rollup;
use App\Support\RollupRead;

class CraftDifficulty
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Crafts and rises in rank by the recipe's difficulty, 0 optimal to 3 trivial, ascending. A craft whose recipe has no
     * difficulty on record is left out, and so is a difficulty fewer than `config('scarlet.min_characters')` characters
     * crafted at.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array{difficulty: int, crafts: int, skill_ups: int}>
     */
    public function handle(Profession $profession, int|array|null $rulesetId = null): array
    {
        $query = $this->read->from(Rollup::CraftsDailyShapeRuleset, $rulesetId)
            ->where('profession_id', $profession->skillLine())
            ->whereNotNull('difficulty')
            ->selectRaw('difficulty, sum(crafts) as crafts, sum(skill_ups) as skill_ups')
            ->groupBy('difficulty')
            ->orderBy('difficulty');

        return array_map(
            fn (array $row): array => ['difficulty' => (int) $row['difficulty'], 'crafts' => (int) $row['crafts'], 'skill_ups' => (int) $row['skill_ups']],
            $this->read->rows($this->read->shown($query)),
        );
    }
}
