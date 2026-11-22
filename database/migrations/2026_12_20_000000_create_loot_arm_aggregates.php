<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The aggregates behind the rule and size charts, the groups page and the one reader of a free-for-all arm (docs/LOOT.md,
 * Developer and agent experience), beside `loot_windows_daily` and `loot_items_daily`. `loot_windows_character_daily`
 * counts windows by source, read, loot rule, group size and character, with the sum and the sum of squares of the item
 * rows a window listed, so an arm's characters are exact and a mean has a variance. `loot_freeforall_items_daily` counts
 * the windows that listed an item for free for all alone, by character, which is all a chart needs to count the one
 * character who read most of a free-for-all arm. `loot_receipts_daily` counts the items a character received from a
 * corpse (a receipt whose source window is known), the numerator of what the looter kept. Built like the aggregates
 * before them: materialized only, a refresh policy, and a refresh over all time here.
 *
 * A continuous aggregate cannot be created inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

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
            'loot_windows_character_daily' => <<<'SQL'
                select time_bucket('1 day', at) as bucket, source_kind, source_id, read, loot_rule, group_size, character_id,
                       count(*) as windows, sum(item_rows) as item_rows, sum(item_rows::bigint * item_rows) as item_rows_squared
                from loot_opens
                where counted and has_items and first_read is not false and source_id is not null
                group by 1, 2, 3, 4, 5, 6, 7
                SQL,
            'loot_freeforall_items_daily' => <<<'SQL'
                select time_bucket('1 day', at) as bucket, source_kind, source_id, read, group_size, character_id, item_id,
                       count(*) as windows
                from loot_drops
                where counted and via = 'window' and first_read is not false and source_id is not null and loot_rule = 'freeforall'
                group by 1, 2, 3, 4, 5, 6, 7
                SQL,
            'loot_receipts_daily' => <<<SQL
                select time_bucket('1 day', at) as bucket, source_kind, source_id, read, loot_rule, group_size,
                       count(*) as receipts, sum(quantity) as quantity, {$hll} as characters
                from loot_drops
                where counted and via = 'receipt' and first_read is not false and source_id is not null
                group by 1, 2, 3, 4, 5, 6
                SQL,
        ];
    }
};
