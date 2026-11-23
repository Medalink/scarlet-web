<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;

/**
 * The days the rollups still have to look at again, as pending ranges under one key. Every change adds its days,
 * merged into the range it touches while the merge stays within `scarlet.rollups.pending_span_days`; a wider range
 * waits as its own. A refresh takes the ranges its own window touches, at most one span of them, so a wide range
 * is handed out span by span to the jobs whose window reaches it instead of riding into the next job that runs,
 * and one burst of uploads costs one pass over the 26 aggregates instead of one pass per day touched.
 */
final class PendingRefresh
{
    private const int DAY = 86400;

    private const int KEPT_SECONDS = 86400;

    /**
     * Widen the pending days with `$from` to `$until` (epoch seconds). `$attempts` is how many jobs have failed on
     * the range; a refresh stops putting a range back once it failed `scarlet.rollups.pending_attempts` times, and
     * the range returns when its data changes again.
     */
    public static function add(int $from, int $until, int $attempts = 0): void
    {
        self::lock()->block(5, function () use ($from, $until, $attempts): void {
            $span = self::spanSeconds();
            $lo = $from;
            $hi = $until;
            $merged = $attempts;
            $kept = [];
            foreach (self::prune(self::peek()) as $chunk) {
                if ($chunk['until'] > $lo && $chunk['from'] < $hi && max($chunk['until'], $hi) - min($chunk['from'], $lo) <= $span) {
                    $lo = min($chunk['from'], $lo);
                    $hi = max($chunk['until'], $hi);
                    $merged = max($merged, $chunk['attempts']);

                    continue;
                }
                $kept[] = $chunk;
            }
            $kept[] = ['from' => $lo, 'until' => $hi, 'attempts' => $merged, 'added' => time()];
            self::put($kept);
        });
    }

    /**
     * Remove and return pending days for the window `$from` to `$until` (epoch seconds): the ranges the window
     * touches, then, while the union stays within one span, the waiting ranges it reaches, so a burst of uploads on
     * nearby days leaves in the one job instead of stranding the ranges whose own job a unique lock folded away;
     * when none touches, the oldest range goes. A range wider than the span gives its first span and keeps the rest.
     * `attempts` is the most often a taken range has failed; `followUp` bounds the ranges that are left for the next
     * run, whose job must be dispatched for them, since they can lie past this job's window. Null when nothing is
     * pending.
     *
     * @return array{from: int, until: int, attempts: int, followUp: array{from: int, until: int}|null}|null
     */
    public static function take(int $from, int $until): ?array
    {
        return self::lock()->block(5, function () use ($from, $until): ?array {
            $span = self::spanSeconds();
            $touching = [];
            $waiting = [];
            foreach (self::prune(self::peek()) as $chunk) {
                if ($chunk['until'] <= $from || $chunk['from'] >= $until) {
                    $waiting[] = $chunk;

                    continue;
                }
                $touching[] = $chunk;
            }
            $lo = null;
            $hi = 0;
            $attempts = 0;
            foreach ($touching as $chunk) {
                $lo = $lo === null ? $chunk['from'] : min($chunk['from'], $lo);
                $hi = max($chunk['until'], $hi);
                $attempts = max($attempts, $chunk['attempts']);
            }
            if ($lo === null) {
                $chunk = array_shift($waiting);
                if ($chunk === null) {
                    self::put([]);

                    return null;
                }
                $lo = $chunk['from'];
                $hi = $chunk['until'];
                $attempts = $chunk['attempts'];
            }
            $rest = [];
            if ($hi - $lo > $span) {
                $rest[] = ['from' => $lo + $span, 'until' => $hi, 'attempts' => $attempts, 'added' => time()];
                $hi = $lo + $span;
            }
            foreach ($waiting as $waitingChunk) {
                if (max($waitingChunk['until'], $hi) - min($waitingChunk['from'], $lo) <= $span) {
                    $lo = min($waitingChunk['from'], $lo);
                    $hi = max($waitingChunk['until'], $hi);
                    $attempts = max($attempts, $waitingChunk['attempts']);

                    continue;
                }
                $rest[] = $waitingChunk;
            }
            self::put($rest);
            $followUp = null;
            foreach ($rest as $restChunk) {
                if ($followUp === null) {
                    $followUp = ['from' => $restChunk['from'], 'until' => $restChunk['until']];

                    continue;
                }
                $followUp['from'] = min($followUp['from'], $restChunk['from']);
                $followUp['until'] = max($followUp['until'], $restChunk['until']);
            }

            return ['from' => $lo, 'until' => $hi, 'attempts' => $attempts, 'followUp' => $followUp];
        });
    }

    /**
     * The ranges whose span since they were last touched ran out; the key expires whole when nothing adds to it.
     *
     * @param  array<int, array{from: int, until: int, attempts: int, added: int}>  $chunks
     * @return list<array{from: int, until: int, attempts: int, added: int}>
     */
    private static function prune(array $chunks): array
    {
        $oldest = time() - self::KEPT_SECONDS;

        return array_values(array_filter($chunks, fn (array $chunk): bool => $chunk['added'] > $oldest));
    }

    /** @return int<1, max> */
    private static function spanSeconds(): int
    {
        return max(1, (int) config('scarlet.rollups.pending_span_days')) * self::DAY;
    }

    /** @param  list<array{from: int, until: int, attempts: int, added: int}>  $chunks */
    private static function put(array $chunks): void
    {
        if ($chunks === []) {
            Cache::store('durable')->forget(self::key());

            return;
        }
        Cache::store('durable')->put(self::key(), $chunks, self::KEPT_SECONDS);
    }

    /** @return array<int, array{from: int, until: int, attempts: int, added: int}> */
    private static function peek(): array
    {
        /** @var list<array{from: int, until: int, attempts: int, added: int}>|null */
        $chunks = Cache::store('durable')->get(self::key());

        return $chunks ?? [];
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
