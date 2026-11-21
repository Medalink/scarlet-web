<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The continuous aggregates of docs/DESIGN.md "Rollups". Each is materialized only (a read never scans the
 * hypertable) and refreshed by a policy and, for the time a session or a removal touched, by ingest
 * (App\Actions\Rollups\RefreshAggregates). A view by character reads every row; a view by ruleset reads the
 * rows of sessions without a plausibility flag (`counted`) and keeps a hyperloglog of the characters
 * that contributed, so a page can hide a bucket that too few characters filled.
 *
 * A continuous aggregate cannot be created inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    /** Characters that contributed, as a hyperloglog with 1024 buckets (about 3 percent error). */
    private const string HLL = 'hyperloglog(1024, character_id)';

    public function up(): void
    {
        foreach ($this->views() as $name => $select) {
            DB::statement(sprintf('create materialized view %s with (timescaledb.continuous, timescaledb.materialized_only = true) as %s with no data', $name, $select));
            DB::statement(
                "select add_continuous_aggregate_policy(?::regclass, start_offset => null, end_offset => interval '1 hour', schedule_interval => interval '15 minutes')",
                [$name],
            );
        }
    }

    /** @return array<string, string> view name => select */
    private function views(): array
    {
        $hll = self::HLL;

        return [
            'xp_daily_character' => <<<'SQL'
                select time_bucket('1 day', at) as bucket, character_id, level, source,
                       sum(amount) as xp, count(*) as gains
                from xp_gains
                group by 1, 2, 3, 4
                SQL,
            'xp_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, level, source,
                       sum(amount) as xp, count(*) as gains, {$hll} as characters
                from xp_gains
                where counted
                group by 1, 2, 3, 4
                SQL,
            'play_hourly_character' => <<<'SQL'
                select time_bucket('1 hour', at) as bucket, character_id, level, zone_id,
                       sum(ms) as ms, sum(afk_ms) as afk_ms, count(*) as spans
                from play_spans
                group by 1, 2, 3, 4
                SQL,
            'play_hourly_ruleset' => <<<SQL
                select time_bucket('1 hour', at) as bucket, ruleset_id, level, zone_id,
                       sum(ms) as ms, sum(afk_ms) as afk_ms, count(*) as spans, {$hll} as characters
                from play_spans
                where counted
                group by 1, 2, 3, 4
                SQL,
            // Time per level and time to reach it, as percentile sketches over the characters that reached
            // the level on that day. `reach` is the played time before the level; `spent` only counts
            // levels the character has left.
            'level_time_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, level,
                       count(*) as reached, {$hll} as characters,
                       tdigest(200, before_seconds::double precision) as reach,
                       tdigest(200, seconds::double precision) filter (where completed) as spent,
                       count(*) filter (where completed) as completed_levels
                from character_levels
                where counted
                group by 1, 2, 3
                SQL,
            'kills_daily_character' => <<<'SQL'
                select time_bucket('1 day', at) as bucket, character_id, npc_id,
                       count(*) as kills, count(*) filter (where credit = 1) as own
                from kills
                where npc_id is not null
                group by 1, 2, 3
                SQL,
            'kills_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, npc_id,
                       count(*) as kills, count(*) filter (where credit = 1) as own, {$hll} as characters
                from kills
                where counted and npc_id is not null
                group by 1, 2, 3
                SQL,
            'deaths_daily_character' => <<<'SQL'
                select time_bucket('1 day', at) as bucket, character_id, level, zone_id, killer_kind, killer,
                       count(*) as deaths
                from deaths
                group by 1, 2, 3, 4, 5, 6
                SQL,
            'deaths_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, level, zone_id, killer_kind, killer,
                       count(*) as deaths, {$hll} as characters
                from deaths
                where counted
                group by 1, 2, 3, 4, 5, 6
                SQL,
            // A run that carries on the run before it (continues_previous) adds its time and bosses to that
            // run and is not a run of its own.
            'dungeons_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, instance_id,
                       count(*) filter (where not continues_previous) as runs,
                       tdigest(100, (inside_ms / 1000.0)::double precision) filter (where not continues_previous) as inside,
                       sum(inside_ms) as inside_ms, sum(bosses) as bosses, sum(deaths) as deaths, {$hll} as characters
                from dungeon_runs
                where counted
                group by 1, 2, 3
                SQL,
            'quests_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, quest_id,
                       count(*) filter (where event = 'turned_in') as turn_ins,
                       count(*) filter (where event = 'accepted') as accepts,
                       count(*) filter (where event = 'abandoned') as abandons,
                       sum(xp) filter (where event = 'turned_in') as xp, {$hll} as characters
                from quest_events
                where counted and quest_id is not null
                group by 1, 2, 3
                SQL,
            'money_daily_character' => <<<'SQL'
                select time_bucket('1 day', at) as bucket, character_id, level,
                       sum(earned) as earned, sum(spent) as spent
                from money_changes
                where src = 'M'
                group by 1, 2, 3
                SQL,
            'money_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, level,
                       sum(earned) as earned, sum(spent) as spent, {$hll} as characters
                from money_changes
                where counted and src = 'M'
                group by 1, 2, 3
                SQL,
            // The drop rate of an item is drops over first opens of its source under one loot rule. A window
            // that was read before (first_read false) lists what is left, so it counts for neither.
            'drops_daily' => <<<SQL
                select time_bucket('1 day', at) as bucket, source_kind, source_id, item_id, loot_rule,
                       count(*) as drops, sum(quantity) as quantity, {$hll} as characters
                from loot_drops
                where counted and via = 'window' and first_read is not false and source_id is not null
                group by 1, 2, 3, 4, 5
                SQL,
            'opens_daily' => <<<SQL
                select time_bucket('1 day', at) as bucket, source_kind, source_id, loot_rule,
                       count(*) as opens, {$hll} as characters
                from loot_opens
                where counted and has_items and first_read is not false and source_id is not null
                group by 1, 2, 3, 4
                SQL,
            'skills_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, kind, skill, skill_id,
                       count(*) as ups, max(skill_rank) as best_rank, {$hll} as characters
                from skill_ups
                where counted
                group by 1, 2, 3, 4, 5
                SQL,
            // Map cells are 100 by 100 on a zone map: x and y are the position times 10000.
            'heat_daily_character' => <<<'SQL'
                select time_bucket('1 day', at) as bucket, character_id, map, x / 100 as cell_x, y / 100 as cell_y,
                       count(*) as points
                from path_points
                where map is not null and x is not null and y is not null
                group by 1, 2, 3, 4, 5
                SQL,
            'heat_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, map, x / 100 as cell_x, y / 100 as cell_y,
                       count(*) as points, {$hll} as characters
                from path_points
                where counted and map is not null and x is not null and y is not null
                group by 1, 2, 3, 4, 5
                SQL,
            'death_heat_daily_character' => <<<'SQL'
                select time_bucket('1 day', at) as bucket, character_id, map_id as map, x / 100 as cell_x, y / 100 as cell_y,
                       count(*) as deaths
                from deaths
                where map_id is not null and x is not null and y is not null
                group by 1, 2, 3, 4, 5
                SQL,
            'death_heat_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, map_id as map, x / 100 as cell_x, y / 100 as cell_y,
                       count(*) as deaths, {$hll} as characters
                from deaths
                where counted and map_id is not null and x is not null and y is not null
                group by 1, 2, 3, 4, 5
                SQL,
        ];
    }
};
