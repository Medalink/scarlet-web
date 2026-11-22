<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the session's `C` and `DM` lines summed to (docs/ROWS.md, `spell_casts` and `meter_totals`): one row per
 * spell with its casts and total cast time, and one row per damage meter with its fights, the largest of them and
 * their seconds. Both fill when the scheduler decodes the stored sessions again for the new decoder version
 * (docs/DESIGN.md, Re-decode).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spell_casts', function (Blueprint $table): void {
            $table->timestampTz('at');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('session_start');
            $table->unsignedInteger('ruleset_id');
            $table->smallInteger('level')->nullable();
            $table->integer('zone_id')->nullable();
            $table->boolean('counted')->default(true);
            $table->integer('spell_id');
            $table->integer('casts');
            $table->bigInteger('cast_ms');
            $table->index(['character_id', 'session_start']);
            $table->index(['character_id', 'at']);
            $table->index(['character_id', 'spell_id']);
        });
        DB::statement('select create_hypertable(?::regclass, by_range(\'at\', ?::interval), create_default_indexes => false)', ['spell_casts', '7 days']);

        Schema::create('meter_totals', function (Blueprint $table): void {
            $table->timestampTz('at');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('session_start');
            $table->unsignedInteger('ruleset_id');
            $table->smallInteger('level')->nullable();
            $table->integer('zone_id')->nullable();
            $table->boolean('counted')->default(true);
            $table->string('meter', 1);
            $table->bigInteger('total');
            $table->bigInteger('best');
            $table->integer('fights');
            $table->bigInteger('fight_ms');
            $table->index(['character_id', 'session_start']);
            $table->index(['character_id', 'at']);
        });
        DB::statement('select create_hypertable(?::regclass, by_range(\'at\', ?::interval), create_default_indexes => false)', ['meter_totals', '7 days']);
    }
};
