<?php

namespace App\Actions\Public;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ItemMarketPrices
{
    /** A market whose newest row for the item is older than this is left out. */
    private const int FRESH_DAYS = 7;

    /**
     * An item's latest AHledger prices on each enabled market (docs/DESIGN.md, Imported market prices): the newest day
     * of each market in the last week, ordered by market id, so by ruleset and then faction. Raw select: `distinct on`
     * has no builder method.
     *
     * @return list<array{market: string, label: string, scanned_at: string, median: int, min_buyout: int, low: int, quantity: int, median_7d: int|null, median_30d: int|null, sold_per_day: float|null}>
     */
    public function handle(int $itemId): array
    {
        $rows = DB::table('market_price_days as p')
            ->join('markets as m', 'm.id', '=', 'p.market_id')
            ->selectRaw('distinct on (p.market_id) p.market_id, m.label, p.scanned_at, p.median, p.min_buyout, p.low, p.quantity, p.median_7d, p.median_30d, p.sold_x10')
            ->where('p.item_id', $itemId)
            ->where('m.enabled', true)
            ->where('p.day', '>=', now()->subDays(self::FRESH_DAYS)->toDateString())
            ->orderBy('p.market_id')
            ->orderByDesc('p.day')
            ->get();

        return array_values(array_map(fn (object $row): array => [
            'market' => (string) $row->market_id,
            'label' => (string) $row->label,
            'scanned_at' => Carbon::parse((string) $row->scanned_at)->utc()->toIso8601ZuluString(),
            'median' => (int) $row->median,
            'min_buyout' => (int) $row->min_buyout,
            'low' => (int) $row->low,
            'quantity' => (int) $row->quantity,
            'median_7d' => $row->median_7d === null ? null : (int) $row->median_7d,
            'median_30d' => $row->median_30d === null ? null : (int) $row->median_30d,
            'sold_per_day' => $row->sold_x10 === null ? null : (int) $row->sold_x10 / 10,
        ], $rows->all()));
    }
}
