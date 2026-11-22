<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A ruleset is a WoW Forever ruleset (docs/DESIGN.md, Rulesets). The rows from before keep existing and name the row
 * that takes their sessions (`merged_into`); each session keeps the row it had (`original_ruleset_id`) and the
 * experience-off signal it reported (`xp_off`, copied from that row), so `rulesets:merge` can be undone. A character's
 * level rows say whether experience was off before or during the level, and the level-time rollup leaves them out of
 * the figure they would skew. Nothing changes for a reader until `rulesets:merge` has run.
 *
 * A continuous aggregate cannot be created inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('rulesets', function (Blueprint $table): void {
            $table->unsignedInteger('merged_into')->nullable();
            $table->foreign('merged_into')->references('id')->on('rulesets');
        });
        Schema::table('sessions', function (Blueprint $table): void {
            $table->unsignedInteger('original_ruleset_id')->nullable();
            $table->boolean('xp_off')->nullable();
        });
        Schema::table('character_levels', function (Blueprint $table): void {
            $table->boolean('xp_off_reach')->default(false);
            $table->boolean('xp_off_spent')->default(false);
        });

        DB::statement('update sessions s set xp_off = r.xp_off from rulesets r where r.id = s.ruleset_id and r.xp_off is not null');

        DB::statement('drop materialized view level_time_ruleset');
        DB::statement(<<<'SQL'
            create materialized view level_time_ruleset with (timescaledb.continuous, timescaledb.materialized_only = true) as
            select time_bucket('1 day', at) as bucket, ruleset_id, level,
                   count(*) filter (where not xp_off_reach) as reached,
                   hyperloglog(1024, character_id) filter (where not xp_off_reach) as characters,
                   tdigest(200, before_seconds::double precision) filter (where not xp_off_reach) as reach,
                   tdigest(200, seconds::double precision) filter (where completed and not xp_off_spent) as spent,
                   count(*) filter (where completed and not xp_off_spent) as completed_levels
            from character_levels
            where counted
            group by 1, 2, 3
            with no data
            SQL);
        DB::statement(
            "select add_continuous_aggregate_policy(?::regclass, start_offset => null, end_offset => interval '1 hour', schedule_interval => interval '15 minutes')",
            ['level_time_ruleset'],
        );
        DB::statement('call refresh_continuous_aggregate(?::regclass, null, null)', ['level_time_ruleset']);
    }
};
