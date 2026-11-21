<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the public gold-by-level rule for partial levels needs (docs/DESIGN.md, "Complete levels"):
 * `money_changes` carries `level_complete`, false until the row's level was played whole — its ding in and ding
 * out seen and no unrecorded play between them — so a character first seen mid-level no longer drags the mean at
 * that level down. The ruleset view reads complete levels only; the character's own reads every row, like
 * `counted`. Existing rows are backfilled from the characters' rebuilt levels and the ruleset view is recreated
 * with the filter and refreshed, so the public means are right as soon as this has run.
 *
 * A continuous aggregate cannot be created inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('money_changes', function (Blueprint $table): void {
            $table->boolean('level_complete')->default(false);
        });

        DB::update(<<<'SQL'
            update money_changes m
            set level_complete = coalesce((
                select cl.completed from character_levels cl
                where cl.character_id = m.character_id and cl.reached = m.level limit 1
            ), false)
            SQL);

        DB::statement('drop materialized view money_daily_ruleset');
        DB::statement(<<<'SQL'
            create materialized view money_daily_ruleset with (timescaledb.continuous, timescaledb.materialized_only = true) as
            select time_bucket('1 day', at) as bucket, ruleset_id, level,
                   sum(earned) as earned, sum(spent) as spent, hyperloglog(1024, character_id) as characters
            from money_changes
            where counted and level_complete and src = 'M'
            group by 1, 2, 3
            with no data
            SQL);
        DB::statement(
            "select add_continuous_aggregate_policy(?::regclass, start_offset => null, end_offset => interval '1 hour', schedule_interval => interval '15 minutes')",
            ['money_daily_ruleset'],
        );
        DB::statement('call refresh_continuous_aggregate(?::regclass, null, null)', ['money_daily_ruleset']);
    }
};
