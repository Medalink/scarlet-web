<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `ingest:sweep` looks for parts that were stored and never decoded. Almost every part is decoded within seconds, so a
 * partial index holds only the waiting ones and the sweep reads a handful of entries however large `parts` grows.
 * Built concurrently, so a running site keeps reading and writing `parts` while it builds.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('create index concurrently if not exists parts_undecoded_created_at_index on parts (created_at) where decoded_at is null');
    }
};
