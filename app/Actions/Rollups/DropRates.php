<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class DropRates
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * What a source drops under each loot rule: drops over first opens of the source. `$sourceKind` is the
     * decoder's source kind: `c` creature, `g` game object, `i` item.
     * The aggregates carry no ruleset, so a drop rate is the same for every ruleset.
     *
     * @return list<array<string, mixed>> item_id, item, loot_rule, drops, quantity, opens, rate, characters
     */
    public function handle(string $sourceKind, int $sourceId): array
    {
        $opens = DB::table(Rollup::OpensDaily->value)
            ->selectRaw('loot_rule, sum(opens) as opens')
            ->where('source_kind', $sourceKind)
            ->where('source_id', $sourceId)
            ->groupBy('loot_rule');

        $query = DB::table(Rollup::DropsDaily->value.' as d')
            ->joinSub($opens, 'o', 'o.loot_rule', '=', 'd.loot_rule')
            ->selectRaw("d.item_id, (select n.name from names n where n.kind = 'item' and n.id = d.item_id order by n.votes desc, n.name limit 1) as item")
            ->selectRaw('d.loot_rule, sum(d.drops) as drops, sum(d.quantity) as quantity, o.opens, sum(d.drops)::float / o.opens as rate')
            ->selectRaw('distinct_count(rollup(d.characters)) as characters')
            ->where('d.source_kind', $sourceKind)
            ->where('d.source_id', $sourceId)
            ->groupBy('d.item_id', 'd.loot_rule', 'o.opens')
            ->havingRaw('distinct_count(rollup(d.characters)) >= ?', [(int) config('scarlet.min_characters')])
            ->orderBy('d.loot_rule')
            ->orderByRaw('sum(d.drops) desc')
            ->orderBy('d.item_id');

        return $this->read->rows($query);
    }
}
