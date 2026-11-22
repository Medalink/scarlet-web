<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The kills a creature page sets its solo loot windows against (docs/LOOT.md, Metrics: Corpses looted of kills). A solo
 * kill is the character's own kill (`credit = 1`) with `group_size` 0 or 1. `group_size` is null without a `loot` part,
 * so every row here comes from a session that holds `combat` and `loot`, and the item lists that give a window its
 * items began on 2026-11-20 at 15:00 in the addon author's time (UTC-6): kills before 21:00 UTC that day are left out,
 * since they have no window to match. Built like the aggregates before it: materialized only, a refresh policy, and a
 * refresh over all time so the stored rows are in the view when the migration ends.
 *
 * A continuous aggregate cannot be created inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement(<<<'SQL'
            create materialized view kills_solo_daily with (timescaledb.continuous, timescaledb.materialized_only = true) as
            select time_bucket('1 day', at) as bucket, npc_id, count(*) as kills, hyperloglog(1024, character_id) as characters
            from kills
            where counted and not player and npc_id is not null and credit = 1 and group_size in (0, 1)
              and at >= timestamptz '2026-11-20 21:00:00+00'
            group by 1, 2
            with no data
            SQL);
        DB::statement(
            "select add_continuous_aggregate_policy('kills_solo_daily'::regclass, start_offset => null, end_offset => interval '1 hour', schedule_interval => interval '15 minutes')",
        );
        DB::statement('call refresh_continuous_aggregate(?::regclass, null, null)', ['kills_solo_daily']);
    }
};
