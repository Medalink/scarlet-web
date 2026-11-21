<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Trigram indexes for the search page: a name is found by any part of it. Built concurrently, so a running
 * site keeps reading and writing these tables while they build.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('create index concurrently if not exists names_name_trgm on names using gin (name gin_trgm_ops)');
        DB::statement('create index concurrently if not exists zones_name_trgm on zones using gin (name gin_trgm_ops)');
        DB::statement('create index concurrently if not exists characters_name_key_trgm on characters using gin (name_key gin_trgm_ops)');
    }
};
