<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The crafting aggregates (docs/DESIGN.md "Rollups"), one grouping each, over the counted `crafts` rows that name a
 * profession. Like every continuous aggregate, this cannot run inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    /** Characters that contributed, as a hyperloglog with 1024 buckets (about 3 percent error). */
    private const string HLL = 'hyperloglog(1024, character_id)';

    /** Ranks are grouped in bands of this many. */
    private const int SKILL_BAND = 25;

    /** The difficulty of a recipe that gives no skill (docs/ROWS.md, `crafts.difficulty`). */
    private const int TRIVIAL = 3;

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
        $band = self::SKILL_BAND;
        $trivial = self::TRIVIAL;

        return [
            'crafts_hourly_ruleset' => <<<SQL
                select time_bucket('1 hour', at) as bucket, ruleset_id, profession_id, level, zone_id,
                       count(*) as crafts,
                       sum(quantity) as made,
                       count(*) filter (where skill_up) as skill_ups,
                       count(*) filter (where difficulty = {$trivial}) as trivial,
                       count(*) filter (where sitting_gap is null) as sittings,
                       tdigest(100, sitting_gap) filter (where sitting_gap is not null) as gap_digest,
                       {$hll} as characters
                from crafts
                where counted and profession_id is not null
                group by 1, 2, 3, 4, 5
                SQL,
            'crafts_daily_shape_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, profession_id, difficulty,
                       (skill / {$band}) * {$band} as skill_band,
                       count(*) as crafts,
                       count(*) filter (where skill_up) as skill_ups,
                       {$hll} as characters
                from crafts
                where counted and profession_id is not null
                group by 1, 2, 3, 4, 5
                SQL,
            'crafts_daily_recipe_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, profession_id, recipe_id, item_id,
                       count(*) as crafts,
                       sum(quantity) as made,
                       count(*) filter (where skill_up) as skill_ups,
                       tdigest(100, skill::double precision) filter (where skill is not null) as skill_digest,
                       {$hll} as characters
                from crafts
                where counted and profession_id is not null and recipe_id is not null
                group by 1, 2, 3, 4, 5
                SQL,
            'crafts_daily_character' => <<<'SQL'
                select time_bucket('1 day', at) as bucket, character_id, ruleset_id, profession_id, level,
                       count(*) as crafts,
                       count(*) filter (where skill_up) as skill_ups,
                       max(skill + coalesce(skill_up, false)::int) as max_skill
                from crafts
                where counted and profession_id is not null
                group by 1, 2, 3, 4, 5
                SQL,
        ];
    }
};
