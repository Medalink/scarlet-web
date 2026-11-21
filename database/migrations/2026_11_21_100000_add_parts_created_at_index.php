<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The account page lists a character's newest parts by upload time. Built concurrently, so a running site keeps
 * reading and writing `parts` while it builds.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('create index concurrently if not exists parts_character_id_created_at_index on parts (character_id, created_at)');
    }
};
