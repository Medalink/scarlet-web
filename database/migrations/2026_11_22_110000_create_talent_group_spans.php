<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The active talent group's spans (docs/ROWS.md), a fact hypertable like the other talent tables: one row when the
 * session's first build is read (`TG` why `s`) and when the active group changes (why `w`). The span's end is the
 * next row of the session or the session's end; the character page's play time per spec group sums them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talent_group_spans', function (Blueprint $table): void {
            $table->timestampTz('at');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('session_start');
            $table->unsignedInteger('ruleset_id');
            $table->smallInteger('level')->nullable();
            $table->integer('zone_id')->nullable();
            $table->boolean('counted')->default(true);
            $table->smallInteger('spec_group');
            $table->index(['character_id', 'session_start']);
            $table->index(['character_id', 'at']);
        });
        DB::statement('select create_hypertable(?::regclass, by_range(\'at\', ?::interval), create_default_indexes => false)', ['talent_group_spans', '7 days']);
    }
};
