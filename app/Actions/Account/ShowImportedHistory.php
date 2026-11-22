<?php

namespace App\Actions\Account;

use App\Support\ImportedKind;
use App\Support\NameKind;
use Illuminate\Support\Facades\DB;

class ShowImportedHistory
{
    private const int DAYS = 30;

    private const int ITEMS = 12;

    /**
     * What the companion imported from other addons (docs/DESIGN.md, Imported history), per character. Read only
     * for the owner's own pages; no board, rollup or public page counts these rows. A character with none is left out.
     *
     * @param  list<int>  $characterIds
     * @return array<int, array<string, mixed>> by character id: sources, first, last, sales, sales_copper, buys, buys_copper, returned, returned_items, gold, days, items
     */
    public function handle(array $characterIds): array
    {
        if ($characterIds === []) {
            return [];
        }

        $out = [];
        $totals = DB::table('imported_records')->whereIn('character_id', $characterIds)->groupBy('character_id')
            ->selectRaw("character_id, min(at) as first, max(at) as last, string_agg(distinct source, ',' order by source) as sources,
                count(*) filter (where kind = 'sale') as sales, coalesce(sum(copper) filter (where kind = 'sale'), 0) as sales_copper,
                count(*) filter (where kind = 'buy') as buys, coalesce(sum(copper) filter (where kind = 'buy'), 0) as buys_copper,
                count(*) filter (where kind in ('expired', 'cancel')) as returned, coalesce(sum(quantity) filter (where kind in ('expired', 'cancel')), 0) as returned_items")
            ->get();
        foreach ($totals as $row) {
            $out[(int) $row->character_id] = [
                'sources' => explode(',', (string) $row->sources),
                'first' => (int) $row->first,
                'last' => (int) $row->last,
                'sales' => (int) $row->sales,
                'sales_copper' => (int) $row->sales_copper,
                'buys' => (int) $row->buys,
                'buys_copper' => (int) $row->buys_copper,
                'returned' => (int) $row->returned,
                'returned_items' => (int) $row->returned_items,
                'gold' => [],
                'days' => [],
                'items' => [],
            ];
        }

        foreach ($this->gold($characterIds) as $id => $points) {
            $out[$id]['gold'] = $points;
        }
        foreach ($this->days($characterIds) as $id => $days) {
            $out[$id]['days'] = $days;
        }
        foreach ($this->items($characterIds) as $id => $items) {
            $out[$id]['items'] = $items;
        }

        return $out;
    }

    /**
     * The last balance of each UTC day, oldest first.
     *
     * @param  list<int>  $characterIds
     * @return array<int, list<array{at: int, balance: int}>>
     */
    private function gold(array $characterIds): array
    {
        $rows = DB::table('imported_records')->whereIn('character_id', $characterIds)->where('kind', ImportedKind::Gold->value)
            ->orderBy('character_id')->orderBy('day_start')->orderBy('at')->get(['character_id', 'day_start', 'at', 'copper']);
        $days = [];
        foreach ($rows as $row) {
            $days[(int) $row->character_id][(int) $row->day_start] = ['at' => (int) $row->at, 'balance' => (int) $row->copper];
        }

        return array_map(fn (array $points): array => array_values($points), $days);
    }

    /**
     * Sales and purchases of the latest days that have any, newest first.
     *
     * @param  list<int>  $characterIds
     * @return array<int, list<array{day: int, sales: int, sales_copper: int, buys: int, buys_copper: int}>>
     */
    private function days(array $characterIds): array
    {
        $rows = DB::table('imported_records')->whereIn('character_id', $characterIds)->whereIn('kind', [ImportedKind::Sale->value, ImportedKind::Buy->value])->groupBy('character_id', 'day_start')
            ->orderBy('character_id')->orderByDesc('day_start')
            ->selectRaw("character_id, day_start, count(*) filter (where kind = 'sale') as sales, coalesce(sum(copper) filter (where kind = 'sale'), 0) as sales_copper,
                count(*) filter (where kind = 'buy') as buys, coalesce(sum(copper) filter (where kind = 'buy'), 0) as buys_copper")
            ->get();
        $days = [];
        foreach ($rows as $row) {
            $id = (int) $row->character_id;
            if (count($days[$id] ?? []) < self::DAYS) {
                $days[$id][] = ['day' => (int) $row->day_start, 'sales' => (int) $row->sales, 'sales_copper' => (int) $row->sales_copper, 'buys' => (int) $row->buys, 'buys_copper' => (int) $row->buys_copper];
            }
        }

        return $days;
    }

    /**
     * What sold for what, per item: units sold and copper received, most copper first.
     *
     * @param  list<int>  $characterIds
     * @return array<int, list<array{item_id: int, name: string, sold: int, copper: int}>>
     */
    private function items(array $characterIds): array
    {
        $rows = DB::table('imported_records')->whereIn('character_id', $characterIds)->where('kind', ImportedKind::Sale->value)->whereNotNull('item_id')->groupBy('character_id', 'item_id')
            ->selectRaw('character_id, item_id, coalesce(sum(quantity), 0) as sold, sum(copper) as copper')
            ->orderBy('character_id')->orderByDesc('copper')
            ->get();
        $picked = [];
        foreach ($rows as $row) {
            $id = (int) $row->character_id;
            if (count($picked[$id] ?? []) < self::ITEMS) {
                $picked[$id][] = $row;
            }
        }

        $ids = [];
        foreach ($picked as $list) {
            foreach ($list as $row) {
                $ids[(int) $row->item_id] = true;
            }
        }
        $names = [];
        foreach (DB::table('names')->where('kind', NameKind::Item->value)->whereIn('id', array_keys($ids))->orderBy('votes')->orderByDesc('name')->get(['id', 'name']) as $row) {
            $names[(int) $row->id] = (string) $row->name;
        }

        $items = [];
        foreach ($picked as $id => $list) {
            foreach ($list as $row) {
                $item = (int) $row->item_id;
                $items[$id][] = ['item_id' => $item, 'name' => $names[$item] ?? 'Item '.$item, 'sold' => (int) $row->sold, 'copper' => (int) $row->copper];
            }
        }

        return $items;
    }
}
