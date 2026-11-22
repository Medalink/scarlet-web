<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `kills_daily_character` counted kills of sessions with a plausibility flag, while every other figure on a
 * character page leaves those sessions out (Medalink/scarlet-web#12). It now reads `counted` rows only, like
 * its ruleset twin `kills_daily_ruleset`, and is refreshed so the most-killed list agrees with the page.
 *
 * A continuous aggregate cannot be created inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('drop materialized view kills_daily_character');
        DB::statement(<<<'SQL'
            create materialized view kills_daily_character with (timescaledb.continuous, timescaledb.materialized_only = true) as
            select time_bucket('1 day', at) as bucket, character_id, npc_id,
                   count(*) as kills, count(*) filter (where credit = 1) as own
            from kills
            where counted and npc_id is not null
            group by 1, 2, 3
            with no data
            SQL);
        DB::statement(
            "select add_continuous_aggregate_policy(?::regclass, start_offset => null, end_offset => interval '1 hour', schedule_interval => interval '15 minutes')",
            ['kills_daily_character'],
        );
        DB::statement('call refresh_continuous_aggregate(?::regclass, null, null)', ['kills_daily_character']);
    }
};
