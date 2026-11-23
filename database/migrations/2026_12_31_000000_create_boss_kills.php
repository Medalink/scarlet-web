<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per boss kill of a session (docs/ROWS.md, `boss_kills`), built by the decoder from the `B` lines and the won
 * `E` ends of the combat group. The table fills when the scheduler decodes the stored sessions again for the new
 * decoder version (docs/DESIGN.md, Re-decode), so no upload format changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boss_kills', function (Blueprint $table): void {
            $table->timestampTz('at');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('session_start');
            $table->unsignedInteger('ruleset_id');
            $table->smallInteger('level')->nullable();
            $table->integer('zone_id')->nullable();
            $table->boolean('counted')->default(true);
            $table->integer('encounter_id')->nullable();
            $table->string('name', 128)->nullable();
            $table->integer('instance_id')->nullable();
            $table->string('source', 1);
            $table->index(['character_id', 'session_start']);
            $table->index(['character_id', 'at']);
        });
        DB::statement('select create_hypertable(?::regclass, by_range(\'at\', ?::interval), create_default_indexes => false)', ['boss_kills', '7 days']);
    }
};
