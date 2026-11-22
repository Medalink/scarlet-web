<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * TimescaleDB stops a transaction that updates more than 100,000 rows of compressed chunks. A job that rewrites the
 * rows of a whole character, or of a week of every character, lifts the limit for its own transaction.
 */
final class DecompressionLimit
{
    /** Lift the limit for the open transaction; the setting ends with it. */
    public static function lift(): void
    {
        DB::statement('set local timescaledb.max_tuples_decompressed_per_dml_transaction = 0');
    }
}
