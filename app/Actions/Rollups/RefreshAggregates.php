<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RefreshAggregates
{
    /** The widest bucket of any aggregate. */
    private const int DAY = 86400;

    /** How long a refresh waits for the one running before it gives up (the job then retries over its pending range). */
    private const int WAIT_SECONDS = 120;

    /** How often one slice retries while a refresh outside this app (TimescaleDB's own policy) holds the aggregate. */
    private const int SLICE_RETRIES = 3;

    private const int SLICE_RETRY_SECONDS = 5;

    private const int LOCK_TTL_EXTRA = 30;

    public function __construct(private readonly RebuildKeyedLoot $keyedLoot) {}

    /**
     * Bring the continuous aggregates up to date over a time range (epoch seconds): the range is widened to whole days,
     * cut to the days the fact hypertables have chunks for, and refreshed in slices of `storage.refresh_slice_days`.
     * Only what TimescaleDB's invalidation log marks as changed in a slice is recomputed, so a range from 1970 to 2100
     * costs the days that hold rows, and a slice that reaches a compressed chunk reads that chunk and no other.
     * TimescaleDB holds an aggregate for the whole window of a refresh, so two refreshes over one aggregate cannot run
     * at once: the callers here (several `RefreshRollups` instances can process together, and commands call this
     * directly) wait for the refresh running on a lock instead of failing with `SQLSTATE 55P03`. A slice that stays
     * locked fails the refresh; `RefreshRollups` keeps its range pending and the job retries.
     * The keyed loot tables are rebuilt whole after the aggregates, under the same lock (`RebuildKeyedLoot`). CALL cannot
     * run inside a transaction, so this must not be called from one.
     */
    public function handle(int $from, int $until): void
    {
        $extent = DB::selectOne("select extract(epoch from min(range_start))::bigint as first, extract(epoch from max(range_end))::bigint as last from timescaledb_information.chunks where hypertable_schema = 'public'");
        if ($extent->first === null) {
            return;
        }

        $start = max(intdiv($from, self::DAY) * self::DAY, intdiv((int) $extent->first, self::DAY) * self::DAY);
        $end = min((intdiv($until, self::DAY) + 1) * self::DAY, (intdiv((int) $extent->last, self::DAY) + 1) * self::DAY);
        $slice = max(1, (int) config('scarlet.storage.refresh_slice_days')) * self::DAY;

        $this->lock()->block(self::WAIT_SECONDS, function () use ($start, $end, $slice): void {
            foreach (Rollup::cases() as $rollup) {
                for ($sliceStart = $start; $sliceStart < $end; $sliceStart += $slice) {
                    $this->slice($rollup->value, $sliceStart, min($sliceStart + $slice, $end));
                }
            }
            $this->keyedLoot->handle();
        });
    }

    /** One CALL on one slice; a slice whose aggregate is locked retries a few times before the refresh fails. */
    private function slice(string $rollup, int $sliceStart, int $sliceEnd): void
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                DB::statement('call refresh_continuous_aggregate(?::regclass, to_timestamp(?), to_timestamp(?))', [$rollup, $sliceStart, $sliceEnd]);

                return;
            } catch (QueryException $failure) {
                if ((string) $failure->getPrevious()?->getCode() !== '55P03' || $attempt >= self::SLICE_RETRIES) {
                    throw $failure;
                }
                sleep(self::SLICE_RETRY_SECONDS);
            }
        }
    }

    private function lock(): Lock
    {
        /** @var LockProvider $store */
        $store = Cache::store('durable')->getStore();

        return $store->lock($this->lockKey(), (int) config('scarlet.rollups.timeout') + self::LOCK_TTL_EXTRA);
    }

    private function lockKey(): string
    {
        return 'rollups-refresh-lock';
    }
}
