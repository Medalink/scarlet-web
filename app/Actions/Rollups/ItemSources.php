<?php

namespace App\Actions\Rollups;

use App\Support\ItemBind;
use App\Support\LootRead;
use App\Support\LootSourceKind;
use App\Support\RateEstimate;
use App\Support\RateFlag;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class ItemSources
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Where an item drops from, by source, loot rule and `read`, with the drop rate (windows that list the item over
     * the windows of the source with that read and rule, first reads only) and the same as an `estimate` (`k` drops of
     * `n` windows with the 95% range). Windows with no `read` are in no row.
     *
     * @return list<array<string, mixed>> source_kind, source_id, source, read, loot_rule, drops, opens, rate, characters, estimate
     */
    public function handle(int $itemId, int $limit = 25): array
    {
        $opens = LootRead::known(DB::table(Rollup::LootWindowsDaily->value))
            ->selectRaw('source_kind, source_id, read, loot_rule, sum(windows) as opens, distinct_count(rollup(characters)) as window_characters')
            ->groupBy('source_kind', 'source_id', 'read', 'loot_rule');

        $query = LootSourceKind::withName(LootRead::known(DB::table(Rollup::LootItemsDaily->value.' as d'), 'd.read')
            ->joinSub($opens, 'o', fn ($join) => $join
                ->on('o.source_kind', '=', 'd.source_kind')
                ->on('o.source_id', '=', 'd.source_id')
                ->on('o.read', '=', 'd.read')
                ->on('o.loot_rule', '=', 'd.loot_rule'))
            ->select('d.source_kind', 'd.source_id', 'd.read'))
            ->selectRaw('d.loot_rule, sum(d.windows) as drops, o.opens, sum(d.windows)::float / o.opens as rate, o.window_characters')
            ->selectRaw('distinct_count(rollup(d.characters)) as characters')
            ->where('d.item_id', $itemId)
            ->groupBy('d.source_kind', 'd.source_id', 'd.read', 'd.loot_rule', 'o.opens', 'o.window_characters')
            ->havingRaw('distinct_count(rollup(d.characters)) >= ?', [(int) config('scarlet.min_characters')])
            ->orderByRaw('sum(d.windows) desc')
            ->orderBy('d.source_id')
            ->orderBy('d.read')
            ->limit($limit);

        $quest = (int) DB::table('items')->where('item_id', $itemId)->value('bind_type') === ItemBind::Quest->value;

        return array_map(function (array $row) use ($quest): array {
            $estimate = RateEstimate::of((int) $row['drops'], (int) $row['opens'], RateFlag::tags((float) $row['window_characters'], $quest));
            unset($row['window_characters']);

            return [...$row, 'estimate' => $estimate];
        }, $this->read->rows($query));
    }
}
