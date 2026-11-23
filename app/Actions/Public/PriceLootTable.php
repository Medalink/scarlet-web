<?php

namespace App\Actions\Public;

use App\Support\ItemBind;
use App\Support\ItemValueSource;
use App\Support\RateSample;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PriceLootTable
{
    /** A market's newest row older than this is left out. */
    private const int FRESH_DAYS = 7;

    private const int POOR = 0;

    /**
     * A drop table of SourceDrops with each row's `value` (copper for one item and where it comes from, or null) and the
     * table's `value`: what its items are worth a window, the sum of quantity times value over the table's windows, null
     * under the windows a rate is stated from or when no row has a value (docs/DESIGN.md, Imported market prices). An
     * item's market value is the median over the enabled markets of each one's newest 7 day median, never under the
     * vendor price. Poor items and items bound when picked up count at the vendor price, quest items at nothing.
     *
     * @param  array{windows: int, rows: list<array<string, mixed>>}  $table
     * @return array{windows: int, rows: list<array<string, mixed>>, value: array{per_window: int|null, unpriced: int, scanned_at: string|null}}
     */
    public function handle(array $table): array
    {
        $ids = array_map(fn (array $row): int => (int) $row['item_id'], $table['rows']);
        $market = $this->market($ids);
        $items = $ids === [] ? collect() : DB::table('items')->whereIn('item_id', $ids)->get(['item_id', 'sell_price', 'bind_type', 'quality'])->keyBy('item_id');

        $sum = 0.0;
        $unpriced = 0;
        $rows = [];
        foreach ($table['rows'] as $row) {
            $item = $items->get((int) $row['item_id']);
            $value = $this->value($market[(int) $row['item_id']] ?? null, $item === null ? null : (array) $item);
            if ($value === null) {
                $unpriced++;
            }
            $sum += $value === null ? 0 : $value['copper'] * (int) $row['quantity'];
            $rows[] = [...$row, 'value' => $value];
        }
        $priced = $unpriced < count($rows);
        $table['rows'] = $rows;
        $table['value'] = [
            'per_window' => $priced && $table['windows'] >= RateSample::HOLLOW_FROM ? (int) round($sum / $table['windows']) : null,
            'unpriced' => $unpriced,
            'scanned_at' => $market === [] ? null : max(array_column($market, 'scanned_at')),
        ];

        return $table;
    }

    /**
     * @param  array{copper: int, scanned_at: string}|null  $market
     * @param  array<string, mixed>|null  $item
     * @return array{copper: int, from: string}|null
     */
    private function value(?array $market, ?array $item): ?array
    {
        $bind = $item === null || $item['bind_type'] === null ? null : ItemBind::tryFrom((int) $item['bind_type']);
        if ($bind === ItemBind::Quest) {
            return null;
        }
        $vendor = (int) ($item['sell_price'] ?? 0);
        $vendorOnly = $bind === ItemBind::Pickup || ($item !== null && $item['quality'] !== null && (int) $item['quality'] === self::POOR);
        if ($market !== null && ! $vendorOnly && $market['copper'] > $vendor) {
            return ['copper' => $market['copper'], 'from' => ItemValueSource::Market->value];
        }

        return $vendor > 0 ? ['copper' => $vendor, 'from' => ItemValueSource::Vendor->value] : null;
    }

    /**
     * Raw select: `distinct on` and `percentile_cont` have no builder methods.
     *
     * @param  list<int>  $ids
     * @return array<int, array{copper: int, scanned_at: string}>
     */
    private function market(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $newest = DB::table('market_price_days as p')
            ->join('markets as m', 'm.id', '=', 'p.market_id')
            ->selectRaw('distinct on (p.market_id, p.item_id) p.item_id, case when p.median_7d > 0 then p.median_7d else p.median end as price, p.scanned_at')
            ->where('m.enabled', true)
            ->whereIn('p.item_id', $ids)
            ->where('p.day', '>=', now()->subDays(self::FRESH_DAYS)->toDateString())
            ->orderBy('p.market_id')->orderBy('p.item_id')->orderByDesc('p.day');
        $rows = DB::query()->fromSub($newest, 'n')
            ->selectRaw('item_id, percentile_cont(0.5) within group (order by price) as copper, max(scanned_at) as scanned_at')
            ->groupBy('item_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->item_id] = ['copper' => (int) round((float) $row->copper), 'scanned_at' => Carbon::parse((string) $row->scanned_at)->utc()->toIso8601ZuluString()];
        }

        return $out;
    }
}
