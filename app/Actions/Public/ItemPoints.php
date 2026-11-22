<?php

namespace App\Actions\Public;

use App\Http\Middleware\PublicApi;
use App\Support\LootListing;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ItemPoints
{
    /**
     * Where an item was looted, as world positions `[u, v]`: the position the character was last at before each window
     * that held the item, newest windows first, at most `LootPoints::DOTS`. Loot, skinning, herbs, ore and containers
     * count; fishing does not. No time and no character comes with a point. Null when no window has a position or fewer
     * than `config('scarlet.min_characters')` characters have one.
     *
     * @return list<array{0: float, 1: float}>|null
     */
    public function handle(int $itemId): ?array
    {
        /** @var list<array{0: float, 1: float}>|null */
        return Cache::remember(self::key($itemId), PublicApi::MAX_AGE, function () use ($itemId): ?array {
            $rows = LootListing::ofSources(DB::table('loot_opens as o'), 'o.read', 'o.source_kind', 'o.source_id')
                ->select('o.character_id', 'o.u', 'o.v')
                ->where('o.counted', true)->where('o.has_items', true)->whereRaw('o.first_read is not false')
                ->whereNotNull('o.u')->whereNotNull('o.v')
                ->whereExists(fn (Builder $held) => $held->selectRaw('1')->from('loot_drops as d')
                    ->whereColumn('d.character_id', 'o.character_id')->whereColumn('d.session_start', 'o.session_start')->whereColumn('d.at', 'o.at')
                    ->whereColumn('d.source_kind', 'o.source_kind')->whereColumn('d.source_id', 'o.source_id')
                    ->where('d.item_id', $itemId)->where('d.via', 'window')->where('d.counted', true))
                ->orderByDesc('o.at')
                ->limit(LootPoints::DOTS)
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all();
            if (count(array_unique(array_column($rows, 'character_id'))) < (int) config('scarlet.min_characters')) {
                return null;
            }

            return array_map(fn (array $row): array => [round((float) $row['u'], 5), round((float) $row['v'], 5)], $rows);
        });
    }

    private static function key(int $itemId): string
    {
        return 'loot-points:item:'.$itemId;
    }
}
