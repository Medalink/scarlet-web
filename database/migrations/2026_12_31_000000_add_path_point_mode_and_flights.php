<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The movement mode of each path point and the flights (`T 1` to `T 0`), from decoder version 15 (docs/ROWS.md). Both
 * fill from a re-decode of stored parts, so no upload format changes. `path_points.mode` is nullable with no default, so
 * rows and compressed chunks written before stay as they are and read null until their session is decoded again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('path_points', function (Blueprint $table): void {
            $table->string('mode', 1)->nullable();
        });

        Schema::create('flights', function (Blueprint $table): void {
            $table->timestampTz('at');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('session_start');
            $table->unsignedInteger('ruleset_id');
            $table->smallInteger('level')->nullable();
            $table->integer('zone_id')->nullable();
            $table->boolean('counted')->default(true);
            $table->integer('map')->nullable();
            $table->integer('x')->nullable();
            $table->integer('y')->nullable();
            $table->integer('to_map')->nullable();
            $table->integer('to_x')->nullable();
            $table->integer('to_y')->nullable();
            $table->unsignedBigInteger('ms')->nullable();
            $table->double('u')->nullable();
            $table->double('v')->nullable();
            $table->double('to_u')->nullable();
            $table->double('to_v')->nullable();
            $table->index(['character_id', 'session_start']);
            $table->index(['character_id', 'at']);
        });
        DB::statement('select create_hypertable(?::regclass, by_range(\'at\', ?::interval), create_default_indexes => false)', ['flights', '7 days']);
    }
};
