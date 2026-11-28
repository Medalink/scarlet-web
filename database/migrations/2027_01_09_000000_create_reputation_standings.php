<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The standing with a faction of docs/ROWS.md, read from the `RF` lines: one row per line, which the client writes when
 * a faction's standing differs from the last line for it. The latest row of each faction is the character's current
 * standing, which the character page reads. The table fills when the scheduler decodes the stored sessions again for
 * the new decoder version (docs/DESIGN.md, Re-decode), so no upload format changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reputation_standings', function (Blueprint $table): void {
            $table->timestampTz('at');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('session_start');
            $table->unsignedInteger('ruleset_id');
            $table->smallInteger('level')->nullable();
            $table->integer('zone_id')->nullable();
            $table->boolean('counted')->default(true);
            $table->unsignedInteger('faction_id');
            $table->smallInteger('reaction')->nullable();
            $table->integer('standing')->nullable();
            $table->integer('floor')->nullable();
            $table->integer('ceiling')->nullable();
            $table->boolean('at_war');
            $table->boolean('account_wide');
            $table->boolean('baseline');
            $table->index(['character_id', 'session_start']);
            $table->index(['character_id', 'faction_id', 'at']);
        });
        DB::statement('select create_hypertable(?::regclass, by_range(\'at\', ?::interval), create_default_indexes => false)', ['reputation_standings', '7 days']);
    }
};
