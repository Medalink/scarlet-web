<?php

namespace App\Actions\Rollups;

use App\Support\Profession;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class CraftRecipeCount
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * How many different recipes the crafts of one profession in the scope name. A craft that names none is not counted,
     * nor is a recipe fewer than `config('scarlet.min_characters')` characters made.
     *
     * @param  int|list<int>|null  $rulesetId
     */
    public function handle(Profession $profession, int|array|null $rulesetId = null): int
    {
        $recipes = $this->read->shown($this->read->from(Rollup::CraftsDailyRecipeRuleset, $rulesetId)
            ->where('profession_id', $profession->skillLine())
            ->whereNotNull('recipe_id')
            ->selectRaw('recipe_id')
            ->groupBy('recipe_id'));

        return (int) $this->read->value(DB::query()->fromSub($recipes, 'r')->selectRaw('count(*) as recipes'), 'recipes');
    }
}
