<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drops `kills_solo_unkeyed_daily`, which `kills_solo_attributed_daily` replaced: it counted only the solo kills that had
 * `credit` 1. Nothing reads it since `KillLoot` moved. Dropping a continuous aggregate removes its refresh policy job
 * with it, and it cannot be dropped inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('drop materialized view if exists kills_solo_unkeyed_daily');
    }
};
