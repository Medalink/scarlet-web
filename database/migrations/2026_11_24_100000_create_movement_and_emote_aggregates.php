<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The movement and emote aggregates (docs/DESIGN.md "Rollups"): distance and time by mode, jumps, and
 * emote uses, by character and by ruleset. Like every continuous aggregate, this cannot run inside a
 * transaction.
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
            'movement_daily_character' => <<<'SQL'
                select time_bucket('1 day', at) as bucket, character_id, mode,
                       count(*) as stretches, sum(yards) as yards, sum(seconds) as seconds, sum(jumps) as jumps
                from movement_stretches
                group by 1, 2, 3
                SQL,
            'movement_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, mode,
                       count(*) as stretches, sum(yards) as yards, sum(seconds) as seconds, sum(jumps) as jumps,
                       {$hll} as characters
                from movement_stretches
                where counted
                group by 1, 2, 3
                SQL,
            'emotes_daily_character' => <<<'SQL'
                select time_bucket('1 day', at) as bucket, character_id, token,
                       count(*) as uses
                from emotes
                group by 1, 2, 3
                SQL,
            'emotes_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, token,
                       count(*) as uses, {$hll} as characters
                from emotes
                where counted
                group by 1, 2, 3
                SQL,
        ];
    }
};
