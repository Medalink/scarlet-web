<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * An index on `loot_opens (source_kind, source_id)`. The pages of one creature, object or container read the windows of one
 * source straight from the table (an object's looter levels, the positions of the newest openings, the level range of
 * each source on `/loot`), and with only the `(character_id, at)` indexes each of those read every chunk.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create index if not exists loot_opens_source_index on loot_opens (source_kind, source_id)');
    }
};
