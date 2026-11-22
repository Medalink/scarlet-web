<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every hit of a death's recap (docs/ROWS.md, `death_hits`) and what the killing blow was: its combat event, its
 * environmental type and the killer's creature id. A hit row has the `at`, `character_id` and `session_start` of its
 * death, so the two tables join on those. Both fill when the scheduler decodes the stored sessions again for the new
 * decoder version (docs/DESIGN.md, Re-decode).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deaths', function (Blueprint $table): void {
            $table->string('event', 32)->nullable();
            $table->string('environmental_type', 16)->nullable();
            $table->unsignedInteger('killer_npc_id')->nullable();
        });

        Schema::create('death_hits', function (Blueprint $table): void {
            $table->timestampTz('at');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('session_start');
            $table->unsignedInteger('ruleset_id');
            $table->smallInteger('level')->nullable();
            $table->integer('zone_id')->nullable();
            $table->boolean('counted')->default(true);
            $table->smallInteger('pos');
            $table->integer('spell_id')->nullable();
            $table->bigInteger('amount')->nullable();
            $table->string('source', 128)->nullable();
            $table->boolean('environmental');
            $table->string('environmental_type', 16)->nullable();
            $table->string('event', 32)->nullable();
            $table->bigInteger('overkill')->nullable();
            $table->bigInteger('hp')->nullable();
            $table->bigInteger('max_hp')->nullable();
            $table->integer('before_ms')->nullable();
            $table->integer('school')->nullable();
            $table->bigInteger('absorbed')->nullable();
            $table->bigInteger('resisted')->nullable();
            $table->bigInteger('blocked')->nullable();
            $table->string('flags', 8)->nullable();
            $table->index(['character_id', 'session_start']);
            $table->index(['character_id', 'at']);
        });
        DB::statement('select create_hypertable(?::regclass, by_range(\'at\', ?::interval), create_default_indexes => false)', ['death_hits', '7 days']);
    }
};
