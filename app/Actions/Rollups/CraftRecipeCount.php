<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\Profession;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class CraftRecipeCount
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * How many different recipes the crafts of one profession in the scope name. A craft that names none is not counted,
     * nor is a recipe fewer than the scope's threshold of characters made.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     */
    public function handle(Profession $profession, int|array|CharacterScope|null $scope = null): int
    {
        $recipes = $this->read->shown($this->read->from(Rollup::CraftsDailyRecipeRuleset, $scope)
            ->where('profession_id', $profession->skillLine())
            ->whereNotNull('recipe_id')
            ->selectRaw('recipe_id')
            ->groupBy('recipe_id'), $scope);

        return (int) $this->read->value(DB::query()->fromSub($recipes, 'r')->selectRaw('count(*) as recipes'), 'recipes');
    }
}
