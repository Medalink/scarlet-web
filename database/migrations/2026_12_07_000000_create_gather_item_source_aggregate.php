<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * What every gather gave, grouped by the creature skinned, the container a catch came in, or the profession itself, so
 * an item page can show where an item is gathered (docs/DESIGN.md "Rollups"). Like every continuous aggregate, this
 * cannot run inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    /** Characters that contributed, as a hyperloglog with 1024 buckets (about 3 percent error). */
    private const string HLL = 'hyperloglog(1024, character_id)';

    public function up(): void
    {
        $hll = self::HLL;
        $select = <<<SQL
            select time_bucket('1 day', at) as bucket, kind, source_id, container_item_id, item_id,
                   count(*) as items,
                   sum(quantity) as quantity,
                   tdigest(100, skill::double precision) filter (where skill is not null) as skill_digest,
                   {$hll} as characters
            from gather_items
            where counted
            group by 1, 2, 3, 4, 5
            SQL;
        DB::statement(sprintf('create materialized view %s with (timescaledb.continuous, timescaledb.materialized_only = true) as %s with no data', 'gather_items_daily_source', $select));
        DB::statement(
            "select add_continuous_aggregate_policy(?::regclass, start_offset => null, end_offset => interval '1 hour', schedule_interval => interval '15 minutes')",
            ['gather_items_daily_source'],
        );
    }
};
