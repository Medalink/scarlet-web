<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupLock;
use Illuminate\Database\QueryException;
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

    public function __construct(private readonly RebuildKeyedLoot $keyedLoot) {}

    /**
     * Bring the continuous aggregates up to date over a time range (epoch seconds): the range is widened to whole days,
     * cut to the days the fact hypertables have chunks for, and refreshed in slices of `storage.refresh_slice_days`.
     * Only what TimescaleDB's invalidation log marks as changed in a slice is recomputed, so a range from 1970 to 2100
     * costs the days that hold rows, and a slice that reaches a compressed chunk reads that chunk and no other.
     * TimescaleDB holds an aggregate for the whole window of a refresh, so two refreshes over one aggregate cannot run
     * at once: the callers here (several `RefreshRollups` instances can process together, and commands call this
     * directly) wait for the refresh running on a lock instead of failing with `SQLSTATE 55P03`. A slice that stays
     * locked fails the refresh; `RefreshRollups` keeps its range pending and goes back on the queue to retry.
     * With `$budgetSeconds` set, the slices stop once the budget is spent and the span that is not refreshed yet comes
     * back, so one call always ends inside the job that called it; the caller puts the span back and a follow-up run
     * continues. The keyed loot tables are rebuilt for the same days after the aggregates, under the same lock
     * (`RebuildKeyedLoot`), also inside the budget. Without a budget the whole range is refreshed in one call.
     * CALL cannot run inside a transaction, so this must not be called from one.
     *
     * @return array{from: int, until: int}|null the span the budget left, null when the refresh finished
     */
    public function handle(int $from, int $until, ?int $budgetSeconds = null): ?array
    {
        $extent = DB::selectOne("select extract(epoch from min(range_start))::bigint as first, extract(epoch from max(range_end))::bigint as last from timescaledb_information.chunks where hypertable_schema = 'public'");
        if ($extent->first === null) {
            return null;
        }

        $start = max(intdiv($from, self::DAY) * self::DAY, intdiv((int) $extent->first, self::DAY) * self::DAY);
        $end = min((intdiv($until, self::DAY) + 1) * self::DAY, (intdiv((int) $extent->last, self::DAY) + 1) * self::DAY);
        $slice = max(1, (int) config('scarlet.storage.refresh_slice_days')) * self::DAY;

        return RollupLock::block(self::WAIT_SECONDS, function () use ($start, $end, $slice, $budgetSeconds): ?array {
            $stop = $budgetSeconds === null ? null : microtime(true) + $budgetSeconds;
            foreach (Rollup::cases() as $rollup) {
                for ($sliceStart = $start; $sliceStart < $end; $sliceStart += $slice) {
                    if ($stop !== null && microtime(true) >= $stop) {
                        return ['from' => $start, 'until' => $end];
                    }
                    $this->slice($rollup->value, $sliceStart, min($sliceStart + $slice, $end));
                }
            }
            if ($stop !== null && microtime(true) >= $stop) {
                return ['from' => $start, 'until' => $end];
            }
            $this->keyedLoot->handle($start, $end);

            return null;
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
}
