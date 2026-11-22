<?php

namespace App\Actions\Rollups;

use App\Support\NameKind;
use App\Support\Profession;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class CraftReagents
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * What the crafts of one profession used: the reagents of each recipe times the crafts of it, most first. A recipe
     * nobody uploaded adds nothing, and neither does one that fewer than `config('scarlet.min_characters')` characters
     * crafted.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array{item_id: int, name: string|null, quantity: int}>
     */
    public function handle(Profession $profession, int|array|null $rulesetId = null, int $limit = 10): array
    {
        $crafted = $this->read->shown(
            $this->read->from(Rollup::CraftsDailyRecipeRuleset, $rulesetId)
                ->where('profession_id', $profession->skillLine())
                ->selectRaw('recipe_id, sum(crafts) as crafts')
                ->groupBy('recipe_id'),
        );
        $query = DB::query()->fromSub($crafted, 'r')
            ->join('recipe_reagents as g', 'g.recipe_spell_id', '=', 'r.recipe_id')
            ->selectRaw('g.item_id, (select n.name from names n where n.kind = ? and n.id = g.item_id order by n.votes desc, n.name limit 1) as name, sum(g.quantity * r.crafts) as quantity', [NameKind::Item->value])
            ->groupBy('g.item_id')
            ->orderByRaw('sum(g.quantity * r.crafts) desc')
            ->orderBy('g.item_id')
            ->limit($limit);

        return array_map(fn (array $row): array => [
            'item_id' => (int) $row['item_id'],
            'name' => $row['name'] === null ? null : (string) $row['name'],
            'quantity' => (int) $row['quantity'],
        ], $this->read->rows($query));
    }
}
