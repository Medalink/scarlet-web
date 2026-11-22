<?php

namespace App\Actions\Rollups;

use App\Support\NameKind;
use App\Support\Profession;
use App\Support\Rollup;
use App\Support\RollupRead;

class CraftRecipes
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The recipes with the most crafts. `name` is the recipe's spell and `item` the item the crafts of it made most often,
     * each null while the dictionary has no name for it, and `item_id` is null when no craft of it named an item.
     * `median_skill` is the median rank before a craft, from a percentile sketch. A recipe fewer than
     * `config('scarlet.min_characters')` characters crafted is left out.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array{recipe_id: int, name: string|null, item_id: int|null, item: string|null, crafts: int, made: int, skill_ups: int, characters: int|null, median_skill: float|null}>
     */
    public function handle(Profession $profession, int|array|null $rulesetId = null, int $limit = 12): array
    {
        $query = $this->read->from(Rollup::CraftsDailyRecipeRuleset, $rulesetId)
            ->where('profession_id', $profession->skillLine())
            ->selectRaw('recipe_id, (select n.name from names n where n.kind = ? and n.id = recipe_id order by n.votes desc, n.name limit 1) as name, sum(crafts) as crafts, coalesce(sum(made), 0) as made, sum(skill_ups) as skill_ups, distinct_count(rollup(characters)) as characters, approx_percentile(0.5, rollup(skill_digest)) as median_skill', [NameKind::Spell->value])
            ->groupBy('recipe_id')
            ->orderByRaw('sum(crafts) desc')
            ->orderBy('recipe_id')
            ->limit($limit);
        $rows = $this->read->rows($this->read->shown($query));
        $items = $this->items($profession, $rulesetId, array_map(fn (array $row): int => (int) $row['recipe_id'], $rows));

        return array_map(fn (array $row): array => [
            'recipe_id' => (int) $row['recipe_id'],
            'name' => $row['name'] === null ? null : (string) $row['name'],
            'item_id' => $items[(int) $row['recipe_id']]['item_id'] ?? null,
            'item' => $items[(int) $row['recipe_id']]['name'] ?? null,
            'crafts' => (int) $row['crafts'],
            'made' => (int) $row['made'],
            'skill_ups' => (int) $row['skill_ups'],
            'characters' => (int) $row['characters'],
            'median_skill' => $row['median_skill'] === null ? null : (float) $row['median_skill'],
        ], $rows);
    }

    /**
     * @param  int|list<int>|null  $rulesetId
     * @param  list<int>  $recipes
     * @return array<int, array{item_id: int, name: string|null}> recipe id => the item its crafts made most often
     */
    private function items(Profession $profession, int|array|null $rulesetId, array $recipes): array
    {
        if ($recipes === []) {
            return [];
        }
        $query = $this->read->from(Rollup::CraftsDailyRecipeRuleset, $rulesetId)
            ->where('profession_id', $profession->skillLine())
            ->whereIn('recipe_id', $recipes)
            ->whereNotNull('item_id')
            ->selectRaw('recipe_id, item_id, (select n.name from names n where n.kind = ? and n.id = item_id order by n.votes desc, n.name limit 1) as name', [NameKind::Item->value])
            ->groupBy('recipe_id', 'item_id')
            ->orderBy('recipe_id')
            ->orderByRaw('sum(crafts) desc')
            ->orderBy('item_id');

        $items = [];
        foreach ($this->read->rows($this->read->shown($query)) as $row) {
            $items[(int) $row['recipe_id']] ??= ['item_id' => (int) $row['item_id'], 'name' => $row['name'] === null ? null : (string) $row['name']];
        }

        return $items;
    }
}
