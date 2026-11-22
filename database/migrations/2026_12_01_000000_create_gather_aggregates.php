<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The gathering aggregates (docs/DESIGN.md "Rollups"), one grouping each so a view never holds the product of every
 * dimension. Like every continuous aggregate, this cannot run inside a transaction. A lure's age is kept in whole
 * minutes, rounded up, so the length of a lure stays a setting of the reader.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    /** Characters that contributed, as a hyperloglog with 1024 buckets (about 3 percent error). */
    private const string HLL = 'hyperloglog(1024, character_id)';

    /** A wait for a bite or a cast is grouped in steps of this many seconds, the last step holding every longer one. */
    private const int WAIT_STEP_SECONDS = 2;

    private const int WAIT_STEPS = 15;

    /** Ranks are grouped in bands of this many. */
    private const int SKILL_BAND = 25;

    /** A lure older than this many minutes is one group, so `GatherKind::LURE_SECONDS` cannot pass 1800. */
    private const int LURE_MINUTES = 30;

    /** A position is the map position times 10000: a cell is 1 percent of the map across. */
    private const int CELL = 100;

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
        $waitSeconds = self::WAIT_STEP_SECONDS;
        $waitSteps = self::WAIT_STEPS;
        $band = self::SKILL_BAND;
        $lureMinutes = self::LURE_MINUTES;
        $cell = self::CELL;

        return [
            'gathers_hourly_ruleset' => <<<SQL
                select time_bucket('1 hour', at) as bucket, ruleset_id, kind, level, zone_id,
                       count(*) as attempts,
                       count(*) filter (where looted) as looted,
                       sum(seconds) as seconds,
                       sum(gap) as gap_seconds,
                       count(*) filter (where gap is null) as trips,
                       count(*) filter (where in_combat) as in_combat,
                       count(*) filter (where skill_up) as skill_ups,
                       count(*) filter (where again is false) as first_strikes,
                       tdigest(100, seconds::double precision) filter (where looted and seconds is not null) as looted_seconds,
                       {$hll} as characters
                from gathers
                where counted
                group by 1, 2, 3, 4, 5
                SQL,
            'gathers_daily_shape_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, kind,
                       case when looted and seconds is not null then least(floor(seconds / {$waitSeconds}), {$waitSteps})::int end as wait_step,
                       (skill / {$band}) * {$band} as skill_band,
                       count(*) filter (where looted) as looted,
                       count(*) filter (where skill_up) as skill_ups,
                       {$hll} as characters
                from gathers
                where counted
                group by 1, 2, 3, 4, 5
                SQL,
            'gathers_daily_source_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, kind, source_id,
                       count(*) as attempts,
                       count(*) filter (where looted) as looted,
                       {$hll} as characters
                from gathers
                where counted and source_id is not null
                group by 1, 2, 3, 4
                SQL,
            'gathers_daily_gear_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, kind, lure_spell,
                       least(ceil(lure_age / 60), {$lureMinutes})::int as lure_minutes,
                       pole_item,
                       count(*) as casts,
                       {$hll} as characters
                from gathers
                where counted and (lure_spell is not null or pole_item is not null)
                group by 1, 2, 3, 4, 5, 6
                SQL,
            'gathers_heat_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, kind, map, x / {$cell} as cell_x, y / {$cell} as cell_y,
                       count(*) as attempts, {$hll} as characters
                from gathers
                where counted and map is not null and x is not null and y is not null
                group by 1, 2, 3, 4, 5, 6
                SQL,
            'gathers_daily_character' => <<<'SQL'
                select time_bucket('1 day', at) as bucket, character_id, ruleset_id, kind, level,
                       count(*) as attempts,
                       count(*) filter (where looted) as looted,
                       count(*) filter (where skill_up) as skill_ups,
                       max(skill + skill_up::int) as max_skill
                from gathers
                where counted
                group by 1, 2, 3, 4, 5
                SQL,
            'gather_items_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, kind, item_id, container_item_id, zone_id,
                       count(*) as items,
                       sum(quantity) as quantity,
                       tdigest(100, skill::double precision) filter (where skill is not null) as skill_digest,
                       {$hll} as characters
                from gather_items
                where counted
                group by 1, 2, 3, 4, 5, 6
                SQL,
        ];
    }
};
