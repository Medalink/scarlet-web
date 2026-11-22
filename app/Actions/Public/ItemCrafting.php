<?php

namespace App\Actions\Public;

use Illuminate\Support\Facades\DB;

class ItemCrafting
{
    private const int LIMIT = 25;

    public function __construct(private readonly RecipeFacts $recipes) {}

    /**
     * The recipes that make an item and the recipes that use it as a reagent.
     *
     * @return array{made_by: list<array<string, mixed>>, used_in: list<array<string, mixed>>}
     */
    public function handle(int $itemId): array
    {
        $makes = DB::table('recipes')->where('output_item_id', $itemId)->orderBy('recipe_spell_id')->limit(self::LIMIT)->pluck('recipe_spell_id')->map(fn (mixed $id): int => (int) $id)->values()->all();
        $uses = DB::table('recipe_reagents')->where('item_id', $itemId)->orderBy('recipe_spell_id')->limit(self::LIMIT)->pluck('recipe_spell_id')->map(fn (mixed $id): int => (int) $id)->values()->all();
        $facts = $this->recipes->handle([...$makes, ...$uses]);

        return [
            'made_by' => array_values(array_intersect_key($facts, array_flip($makes))),
            'used_in' => array_values(array_intersect_key($facts, array_flip($uses))),
        ];
    }
}
