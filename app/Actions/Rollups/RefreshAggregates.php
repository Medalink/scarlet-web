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
     * Bring the continuous aggregates up to date over a time range (epoch seconds, `$until` excluded once it is a
     * whole day): the range is widened to whole days and refreshed in slices of `storage.refresh_slice_days`, taken
     * only from the days the fact hypertables have chunks for, so a range from 1970 to 2100 costs the slices that hold
     * rows. Only what TimescaleDB's invalidation log marks as changed in a slice is recomputed, and a slice that
     * reaches a compressed chunk reads that chunk and no other.
     * TimescaleDB holds an aggregate for the whole window of a refresh, so two refreshes over one aggregate cannot run
     * at once: the callers here (several `RefreshRollups` instances can process together, and commands call this
     * directly) wait for the refresh running on a lock instead of failing with `SQLSTATE 55P03`. A slice that stays
     * locked fails the refresh; `RefreshRollups` keeps its range pending and goes back on the queue to retry.
     * A slice is refreshed for every aggregate before the next slice starts. With `$budgetSeconds` set, no new slice
     * starts once the budget is spent (the first always does) and the span from the slice that did not start to the
     * end of the range comes back, so one call ends inside the job that called it and a follow-up over the span
     * continues where this one stopped. The keyed loot tables are rebuilt after the aggregates for the days the
     * slices that ran cover, under the same lock (`RebuildKeyedLoot`). Without a budget the whole range is refreshed
     * in one call. CALL cannot run inside a transaction, so this must not be called from one.
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
        $width = max(1, (int) config('scarlet.storage.refresh_slice_days')) * self::DAY;

        return RollupLock::block(self::WAIT_SECONDS, function () use ($start, $end, $width, $budgetSeconds): ?array {
            $stop = $budgetSeconds === null ? null : microtime(true) + $budgetSeconds;
            $left = null;
            $reached = $start;
            foreach ($this->slices($start, $end, $width) as $position => [$sliceStart, $sliceEnd]) {
                if ($position > 0 && $stop !== null && microtime(true) >= $stop) {
                    $left = ['from' => $sliceStart, 'until' => $end];
                    break;
                }
                foreach (Rollup::cases() as $rollup) {
                    $this->slice($rollup->value, $sliceStart, $sliceEnd);
                }
                $reached = $sliceEnd;
            }
            $this->keyedLoot->handle($start, $left === null ? $end : $reached);

            return $left;
        });
    }

    /**
     * The slices of `[$start, $end)` that hold a chunk of any hypertable, each at most `$width` long, oldest first.
     *
     * @return list<array{int, int}>
     */
    private function slices(int $start, int $end, int $width): array
    {
        $chunks = DB::select("select distinct extract(epoch from range_start)::bigint as lo, extract(epoch from range_end)::bigint as hi from timescaledb_information.chunks where hypertable_schema = 'public' and range_start < to_timestamp(?) and range_end > to_timestamp(?) order by 1, 2", [$end, $start]);
        $ranges = [];
        foreach ($chunks as $chunk) {
            $lo = max($start, intdiv((int) $chunk->lo, self::DAY) * self::DAY);
            $hi = min($end, (intdiv((int) $chunk->hi - 1, self::DAY) + 1) * self::DAY);
            $last = array_key_last($ranges);
            if ($last !== null && $lo <= $ranges[$last][1]) {
                $ranges[$last][1] = max($ranges[$last][1], $hi);

                continue;
            }
            $ranges[] = [$lo, $hi];
        }
        $slices = [];
        foreach ($ranges as [$lo, $hi]) {
            for ($sliceStart = $lo; $sliceStart < $hi; $sliceStart += $width) {
                $slices[] = [$sliceStart, min($sliceStart + $width, $hi)];
            }
        }

        return $slices;
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
