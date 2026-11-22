<?php

namespace App\Actions\Public;

use App\Models\Realm;
use App\Models\Ruleset;
use App\Support\AuctionPriceKind;
use Illuminate\Support\Facades\DB;

class ItemPrices
{
    /** How far back the history reaches. */
    private const int DAYS = 365;

    /** The most markets one page lists, the ones with the most days first. */
    private const int MARKETS = 6;

    /**
     * The lowest auction price seen for an item on each UTC day, one list for each ruleset and realm, so prices of
     * different markets never mix. A day's price is the lowest of its commodity and item searches; a browse row stands
     * in only for a day with no search. Rows of flagged sessions are left out, like the ruleset rollups.
     *
     * @return list<array{ruleset: string, realm: string|null, days: list<array{day: string, price: int, browse: bool}>}>
     */
    public function handle(int $itemId): array
    {
        $rows = DB::table('auction_prices')
            ->selectRaw("ruleset_id, realm_id, (at at time zone 'UTC')::date as day")
            ->selectRaw('min(price) filter (where kind in (?, ?)) as searched', AuctionPriceKind::searches())
            ->selectRaw('min(price) filter (where kind = ?) as browsed', [AuctionPriceKind::Browse->value])
            ->where('item_id', $itemId)
            ->where('counted', true)
            ->where('price', '>', 0)
            ->whereIn('kind', [...AuctionPriceKind::searches(), AuctionPriceKind::Browse->value])
            ->where('at', '>=', now()->subDays(self::DAYS))
            ->groupBy('ruleset_id', 'realm_id')
            ->groupByRaw("(at at time zone 'UTC')::date")
            ->orderBy('ruleset_id')->orderBy('realm_id')->orderBy('day')
            ->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $markets = [];
        foreach ($rows as $row) {
            $browse = $row->searched === null;
            $markets[$row->ruleset_id.':'.$row->realm_id]['ruleset_id'] = (int) $row->ruleset_id;
            $markets[$row->ruleset_id.':'.$row->realm_id]['realm_id'] = $row->realm_id === null ? null : (int) $row->realm_id;
            $markets[$row->ruleset_id.':'.$row->realm_id]['days'][] = ['day' => (string) $row->day, 'price' => (int) ($browse ? $row->browsed : $row->searched), 'browse' => $browse];
        }
        uasort($markets, fn (array $a, array $b): int => count($b['days']) <=> count($a['days']));
        $markets = array_slice($markets, 0, self::MARKETS);

        $rulesets = Ruleset::query()->whereIn('id', array_column($markets, 'ruleset_id'))->get()->keyBy('id');
        $realms = Realm::query()->whereIn('id', array_filter(array_column($markets, 'realm_id'), fn (?int $id): bool => $id !== null))->pluck('name', 'id');

        return array_values(array_map(fn (array $market): array => [
            'ruleset' => $rulesets->get($market['ruleset_id'])?->label() ?? Ruleset::UNIDENTIFIED_LABEL,
            'realm' => $market['realm_id'] === null ? null : ($realms->get($market['realm_id']) ?? 'Realm '.$market['realm_id']),
            'days' => $market['days'],
        ], $markets));
    }
}
