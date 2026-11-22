<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `kills_solo_attributed_daily`: the solo kills with no corpse key that `kills_solo_unkeyed_daily` counted, now with the
 * kills the uploader's own threat list got (`credit` 2: a pet or a damage-over-time spell made the last hit) as well as
 * the ones it dealt the killing blow to (`credit` 1). A solo window is counted whoever made the last hit, so counting
 * only `credit` 1 as kills put fewer kills under the windows than there were (docs/LOOT.md, Metrics). A continuous
 * aggregate cannot change its definition, so the new one is built beside the old and the readers move before the old is
 * dropped. A continuous aggregate cannot be created inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement(<<<'SQL'
            create materialized view kills_solo_attributed_daily with (timescaledb.continuous, timescaledb.materialized_only = true) as
            select time_bucket('1 day', at) as bucket, npc_id, count(*) as kills, hyperloglog(1024, character_id) as characters
            from kills
            where counted and not player and npc_id is not null and credit in (1, 2) and group_size in (0, 1)
              and at >= timestamptz '2026-11-20 21:00:00+00' and corpse_key is null
            group by 1, 2
            with no data
            SQL);
        DB::statement(
            "select add_continuous_aggregate_policy('kills_solo_attributed_daily'::regclass, start_offset => null, end_offset => interval '1 hour', schedule_interval => interval '15 minutes')",
        );
        DB::statement("call refresh_continuous_aggregate('kills_solo_attributed_daily'::regclass, null, null)");
    }
};
