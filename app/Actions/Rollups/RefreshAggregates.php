<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use Illuminate\Support\Facades\DB;

class RefreshAggregates
{
    /** The widest bucket of any aggregate. */
    private const int DAY = 86400;

    /**
     * Bring the continuous aggregates up to date over a time range (epoch seconds): the range is widened to whole days,
     * cut to the days the fact hypertables have chunks for, and refreshed in slices of `storage.refresh_slice_days`.
     * Only what TimescaleDB's invalidation log marks as changed in a slice is recomputed, so a range from 1970 to 2100
     * costs the days that hold rows, and a slice that reaches a compressed chunk reads that chunk and no other.
     * CALL cannot run inside a transaction, so this must not be called from one.
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

        foreach (Rollup::cases() as $rollup) {
            for ($sliceStart = $start; $sliceStart < $end; $sliceStart += $slice) {
                DB::statement('call refresh_continuous_aggregate(?::regclass, to_timestamp(?), to_timestamp(?))', [$rollup->value, $sliceStart, min($sliceStart + $slice, $end)]);
            }
        }
    }
}
