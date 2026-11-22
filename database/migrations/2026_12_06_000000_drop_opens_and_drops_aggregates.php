<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drops `drops_daily` and `opens_daily`, which `loot_windows_daily` and `loot_items_daily` replaced. Nothing reads them
 * any more: the drop tables, the item totals, search and the public API read the new aggregates (docs/LOOT.md,
 * "Developer and agent experience"). Dropping a continuous aggregate removes its refresh policy job with it.
 *
 * A continuous aggregate cannot be dropped inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('drop materialized view if exists drops_daily');
        DB::statement('drop materialized view if exists opens_daily');
    }
};
