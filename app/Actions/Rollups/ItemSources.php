<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class ItemSources
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Where an item drops from, by source and loot rule, with the drop rate (drops over first opens).
     *
     * @return list<array<string, mixed>> source_kind, source_id, source, loot_rule, drops, opens, rate, characters
     */
    public function handle(int $itemId, int $limit = 25): array
    {
        $opens = DB::table(Rollup::OpensDaily->value)
            ->selectRaw('source_kind, source_id, loot_rule, sum(opens) as opens')
            ->groupBy('source_kind', 'source_id', 'loot_rule');

        $query = DB::table(Rollup::DropsDaily->value.' as d')
            ->joinSub($opens, 'o', fn ($join) => $join
                ->on('o.source_kind', '=', 'd.source_kind')
                ->on('o.source_id', '=', 'd.source_id')
                ->on('o.loot_rule', '=', 'd.loot_rule'))
            ->selectRaw("d.source_kind, d.source_id, (select n.name from names n where n.kind = 'npc' and d.source_kind = 'c' and n.id = d.source_id order by n.votes desc, n.name limit 1) as source")
            ->selectRaw('d.loot_rule, sum(d.drops) as drops, o.opens, sum(d.drops)::float / o.opens as rate')
            ->selectRaw('distinct_count(rollup(d.characters)) as characters')
            ->where('d.item_id', $itemId)
            ->groupBy('d.source_kind', 'd.source_id', 'd.loot_rule', 'o.opens')
            ->havingRaw('distinct_count(rollup(d.characters)) >= ?', [(int) config('scarlet.min_characters')])
            ->orderByRaw('sum(d.drops) desc')
            ->orderBy('d.source_id')
            ->limit($limit);

        return $this->read->rows($query);
    }
}
