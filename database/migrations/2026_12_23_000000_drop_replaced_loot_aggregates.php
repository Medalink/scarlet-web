<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drops `loot_windows_daily`, `loot_freeforall_items_daily` and `kills_solo_daily`, which the counted views and the
 * unkeyed aggregates replaced (docs/LOOT.md, Developer and agent experience). Nothing reads them: the drop tables, the
 * arms, search and the creature answer read `loot_windows_counted`, `loot_items_counted`, `loot_windows_character_counted`
 * and the keyed kill tables. `loot_items_daily`, `loot_windows_character_daily` and `loot_receipts_daily` stay for What
 * the looter kept and Your share of group loot. Dropping a continuous aggregate removes its refresh policy job with it.
 *
 * A continuous aggregate cannot be dropped inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('drop materialized view if exists loot_windows_daily');
        DB::statement('drop materialized view if exists loot_freeforall_items_daily');
        DB::statement('drop materialized view if exists kills_solo_daily');
    }
};
