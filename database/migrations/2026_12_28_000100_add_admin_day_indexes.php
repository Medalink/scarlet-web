<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The admin pages count parts by upload day and sessions by decode day over the last 90 days (docs/DESIGN.md, Admin).
 * Neither table had an index led by that time, so each count read the whole table, which grows with every upload.
 * Built concurrently, so a running site keeps reading and writing both tables while they build.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('create index concurrently if not exists parts_created_at_index on parts (created_at)');
        DB::statement('create index concurrently if not exists sessions_decoded_at_index on sessions (decoded_at)');
    }
};
