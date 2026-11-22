<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The loot aggregates of docs/LOOT.md "Developer and agent experience", beside `opens_daily` and `drops_daily`, which
 * they replace once the callers have moved. `loot_windows_daily` counts first reads of a source (a window is one
 * character's first read of one corpse or object) and `loot_items_daily` counts, in the same keys plus the item, the
 * windows that listed it. Both carry `read`, so a drop table reads `read = 'loot'` and a skinning table `read = 'skin'`;
 * windows of sessions not decoded at version 10 yet have a null `read` and stay in the view for the readers to leave
 * out (docs/LOOT.md, Metrics). They are built like the aggregates before them: materialized only, a refresh policy, and
 * a refresh over all time here, so the rows already stored are in the view when the migration ends.
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
            DB::statement('call refresh_continuous_aggregate(?::regclass, null, null)', [$name]);
        }
    }

    /** @return array<string, string> view name => select */
    private function views(): array
    {
        $hll = self::HLL;

        return [
            'loot_windows_daily' => <<<SQL
                select time_bucket('1 day', at) as bucket, source_kind, source_id, read, loot_rule, group_size, zone_id,
                       count(*) as windows, sum(item_rows) as item_rows, {$hll} as characters
                from loot_opens
                where counted and has_items and first_read is not false and source_id is not null
                group by 1, 2, 3, 4, 5, 6, 7
                SQL,
            'loot_items_daily' => <<<SQL
                select time_bucket('1 day', at) as bucket, source_kind, source_id, read, loot_rule, group_size, zone_id, item_id,
                       count(*) as windows, sum(quantity) as quantity, {$hll} as characters
                from loot_drops
                where counted and via = 'window' and first_read is not false and source_id is not null
                group by 1, 2, 3, 4, 5, 6, 7, 8
                SQL,
        ];
    }
};
