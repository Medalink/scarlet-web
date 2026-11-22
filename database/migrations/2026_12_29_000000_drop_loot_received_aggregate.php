<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drops `loot_received_daily`, which counted every receipt in a group of 2 or more, quest rewards and pushed items
 * included. Your share of group loot reads `loot_drops` receipts with a source window or an own won roll instead, and
 * nothing reads the aggregate. Dropping a continuous aggregate removes its refresh policy job with it, and it cannot be
 * dropped inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('drop materialized view if exists loot_received_daily');
    }
};
