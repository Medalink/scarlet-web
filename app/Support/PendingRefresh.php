<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;

/**
 * The days the rollups still have to look at again. Every change adds its days; the one refresh job takes them all,
 * so a burst of uploads costs one pass over the 26 aggregates instead of one pass per day touched.
 */
final class PendingRefresh
{
    private const int KEPT_SECONDS = 86400;

    /** Widen the pending range to take in `$from` to `$until` (epoch seconds). */
    public static function add(int $from, int $until): void
    {
        self::lock()->block(5, function () use ($from, $until): void {
            $kept = self::peek();
            Cache::store('durable')->put(self::key(), [
                'from' => $kept === null ? $from : min($kept['from'], $from),
                'until' => $kept === null ? $until : max($kept['until'], $until),
            ], self::KEPT_SECONDS);
        });
    }

    /**
     * Remove and return the pending range, null when nothing is pending.
     *
     * @return array{from: int, until: int}|null
     */
    public static function take(): ?array
    {
        return self::lock()->block(5, function (): ?array {
            $kept = self::peek();
            Cache::store('durable')->forget(self::key());

            return $kept;
        });
    }

    /** @return array{from: int, until: int}|null */
    private static function peek(): ?array
    {
        /** @var array{from: int, until: int}|null */
        return Cache::store('durable')->get(self::key());
    }

    private static function lock(): Lock
    {
        /** @var LockProvider $store */
        $store = Cache::store('durable')->getStore();

        return $store->lock(self::lockKey(), 10);
    }

    private static function key(): string
    {
        return 'rollups-pending';
    }

    private static function lockKey(): string
    {
        return 'rollups-pending-lock';
    }
}
