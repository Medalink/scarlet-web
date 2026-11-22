<?php

namespace App\Actions\Public;

use App\Http\Middleware\PublicApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LootPoints
{
    /** At most this many of the newest windows are marked. */
    public const int DOTS = 600;

    /**
     * Where a source was looted, as world positions `[u, v]`: the position the character was last at before the window
     * (`loot_opens.u`, `v`), newest windows first. No time and no character comes with a point. Null when no window has a
     * position or fewer than `config('scarlet.min_characters')` characters have one.
     *
     * @return list<array{0: float, 1: float}>|null
     */
    public function handle(string $sourceKind, int $sourceId): ?array
    {
        $key = 'loot-points:'.$sourceKind.':'.$sourceId;

        /** @var list<array{0: float, 1: float}>|null */
        return Cache::remember($key, PublicApi::MAX_AGE, function () use ($sourceKind, $sourceId): ?array {
            $rows = DB::table('loot_opens')
                ->select('character_id', 'u', 'v')
                ->where('counted', true)->where('has_items', true)->whereRaw('first_read is not false')
                ->where('source_kind', $sourceKind)->where('source_id', $sourceId)
                ->whereNotNull('u')->whereNotNull('v')
                ->orderByDesc('at')
                ->limit(self::DOTS)
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all();
            if (count(array_unique(array_column($rows, 'character_id'))) < (int) config('scarlet.min_characters')) {
                return null;
            }

            return array_map(fn (array $row): array => [round((float) $row['u'], 5), round((float) $row['v'], 5)], $rows);
        });
    }
}
