<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;

/**
 * The one lock under which the continuous aggregates and the keyed loot tables are refreshed. A refresh of an aggregate
 * holds it for the whole window, and a rebuild of the keyed tables deletes and inserts the same days, so two of either
 * cannot run at once.
 */
final class RollupLock
{
    private const int EXTRA_SECONDS = 30;

    /** Run `$work` holding the lock and return what it returns, waiting up to `$waitSeconds` for the holder before the wait fails. */
    public static function block(int $waitSeconds, Closure $work): mixed
    {
        /** @var LockProvider $store */
        $store = Cache::store('durable')->getStore();

        return $store->lock(self::lockKey(), (int) config('scarlet.rollups.timeout') + self::EXTRA_SECONDS)->block($waitSeconds, $work);
    }

    private static function lockKey(): string
    {
        return 'rollups-refresh-lock';
    }
}
