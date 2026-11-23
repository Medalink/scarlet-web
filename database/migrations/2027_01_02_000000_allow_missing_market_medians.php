<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AHledger leaves the 7 and 30 day medians empty for an item first listed in the last scan (docs/DESIGN.md, Imported
 * market prices). Raw SQL: dropping NOT NULL changes only the catalog, and the schema builder's change() rewrites the
 * whole column definition.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table market_price_days alter column median_7d drop not null');
        DB::statement('alter table market_price_days alter column median_30d drop not null');
    }
};
