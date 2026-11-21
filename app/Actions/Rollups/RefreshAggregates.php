<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use Illuminate\Support\Facades\DB;

class RefreshAggregates
{
    /** The widest bucket of any aggregate. */
    private const int DAY = 86400;

    /**
     * Bring the continuous aggregates up to date over a time range (epoch seconds): the range is widened to
     * whole days, and only what TimescaleDB's invalidation log marks as changed in it is recomputed.
     * CALL cannot run inside a transaction, so this must not be called from one.
     */
    public function handle(int $from, int $until): void
    {
        $start = intdiv($from, self::DAY) * self::DAY;
        $end = (intdiv($until, self::DAY) + 1) * self::DAY;

        foreach (Rollup::cases() as $rollup) {
            DB::statement('call refresh_continuous_aggregate(?::regclass, to_timestamp(?), to_timestamp(?))', [$rollup->value, $start, $end]);
        }
    }
}
