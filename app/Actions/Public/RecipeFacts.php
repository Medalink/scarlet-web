<?php

namespace App\Actions\Public;

use App\Support\NameKind;
use Illuminate\Support\Facades\DB;

class RecipeFacts
{
    public function __construct(private readonly ResolveNames $names) {}

    /**
     * What each recipe makes and needs, from the uploaded `RQ` lines. Recipes nobody uploaded are left out.
     *
     * @param  list<int>  $spellIds  the recipes' spell ids
     * @return array<int, array{spell_id: int, name: string, output: array{item_id: int|null, name: string|null, min: int|null, max: int|null}, optional_slots: int|null, reagents: list<array{item_id: int, name: string, quantity: int}>}>
     */
    public function handle(array $spellIds): array
    {
        if ($spellIds === []) {
            return [];
        }
        $recipes = DB::table('recipes')->whereIn('recipe_spell_id', $spellIds)->orderBy('recipe_spell_id')
            ->get(['recipe_spell_id', 'output_item_id', 'min_quantity', 'max_quantity', 'optional_slots']);
        $reagents = DB::table('recipe_reagents')->whereIn('recipe_spell_id', $spellIds)->orderBy('item_id')->get(['recipe_spell_id', 'item_id', 'quantity']);

        $itemIds = [];
        foreach ($recipes as $row) {
            $itemIds[] = $row->output_item_id === null ? null : (int) $row->output_item_id;
        }
        foreach ($reagents as $row) {
            $itemIds[] = (int) $row->item_id;
        }
        $items = $this->names->handle(NameKind::Item, $itemIds);
        $spells = $this->names->handle(NameKind::Spell, $spellIds);

        $needs = [];
        foreach ($reagents as $row) {
            $needs[(int) $row->recipe_spell_id][] = ['item_id' => (int) $row->item_id, 'name' => $items[(int) $row->item_id] ?? 'Item '.$row->item_id, 'quantity' => (int) $row->quantity];
        }

        $facts = [];
        foreach ($recipes as $row) {
            $id = (int) $row->recipe_spell_id;
            $output = $row->output_item_id === null ? null : (int) $row->output_item_id;
            $facts[$id] = [
                'spell_id' => $id,
                'name' => $spells[$id] ?? 'Spell '.$id,
                'output' => [
                    'item_id' => $output,
                    'name' => $output === null ? null : ($items[$output] ?? 'Item '.$output),
                    'min' => $row->min_quantity === null ? null : (int) $row->min_quantity,
                    'max' => $row->max_quantity === null ? null : (int) $row->max_quantity,
                ],
                'optional_slots' => $row->optional_slots === null ? null : (int) $row->optional_slots,
                'reagents' => $needs[$id] ?? [],
            ];
        }

        return $facts;
    }
}
