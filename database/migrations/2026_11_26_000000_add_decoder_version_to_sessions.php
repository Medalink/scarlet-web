<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The decoder version that wrote a session's rows (`DECODER_VERSION` in part/src/decode.rs). Null for a session stored
 * before the decoder reported it, which `ingest:redecode --outdated` treats as outdated (docs/DESIGN.md, Re-decode).
 * The index lets that run find the outdated sessions without reading the table, and is built concurrently so the site
 * keeps writing sessions while it builds.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasColumn('sessions', 'decoder_version')) {
            Schema::table('sessions', function (Blueprint $table): void {
                $table->unsignedSmallInteger('decoder_version')->nullable();
            });
        }
        DB::statement('create index concurrently if not exists sessions_decoder_version_index on sessions (decoder_version)');
    }
};
