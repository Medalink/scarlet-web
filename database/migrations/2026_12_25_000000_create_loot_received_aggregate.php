<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `loot_received_daily`: every item an uploader received while in a group of 2 or more, by loot rule and group size
 * (docs/LOOT.md, Metrics: Share of group loot). Unlike `loot_receipts_daily` it keeps a receipt whatever its source: a
 * roll win lands after the loot window closed, a skin read opens a corpse a second time, and neither has a first-read
 * source window, yet the group mates' `LT` rows that Your share of group loot sets them against count both. Built like
 * the aggregates before it: materialized only, a refresh policy, and a refresh over all time here.
 *
 * A continuous aggregate cannot be created inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement(<<<'SQL'
            create materialized view loot_received_daily with (timescaledb.continuous, timescaledb.materialized_only = true) as
            select time_bucket('1 day', at) as bucket, loot_rule, group_size,
                   count(*) as receipts, hyperloglog(1024, character_id) as characters
            from loot_drops
            where counted and via = 'receipt' and group_size >= 2
            group by 1, 2, 3
            with no data
            SQL);
        DB::statement(
            "select add_continuous_aggregate_policy('loot_received_daily'::regclass, start_offset => null, end_offset => interval '1 hour', schedule_interval => interval '15 minutes')",
        );
        DB::statement("call refresh_continuous_aggregate('loot_received_daily'::regclass, null, null)");
    }
};
