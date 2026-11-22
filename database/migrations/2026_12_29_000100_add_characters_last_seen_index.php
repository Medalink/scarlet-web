<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * /admin/characters lists characters the most recently seen first (docs/DESIGN.md, Admin), so a page reads the first
 * 50 entries of this index instead of sorting the whole table. Built concurrently, so a running site keeps reading and
 * writing `characters` while it builds.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('create index concurrently if not exists characters_last_seen_at_index on characters (last_seen_at desc nulls last, id desc)');
    }
};
