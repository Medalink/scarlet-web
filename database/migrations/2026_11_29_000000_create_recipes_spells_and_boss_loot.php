<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The lines `RQ`, `SI`, `SD` and `EL` (Medalink/scarlet-web#18; docs/ROWS.md). `recipes` and `spells` are game
 * reference tables, the same for every uploader, so each id keeps its newest row. `boss_loot` is a fact table:
 * the notice reaches every member of an uploader's group, so each uploader has a row and the pages count a drop once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table): void {
            $table->integer('recipe_spell_id')->primary();
            $table->integer('output_item_id')->nullable()->index();
            $table->integer('min_quantity')->nullable();
            $table->integer('max_quantity')->nullable();
            $table->smallInteger('optional_slots')->nullable();
            $table->smallInteger('type')->nullable();
            $table->timestampTz('updated_at')->useCurrent();
        });

        Schema::create('recipe_reagents', function (Blueprint $table): void {
            $table->integer('recipe_spell_id');
            $table->integer('item_id');
            $table->integer('quantity');
            $table->primary(['recipe_spell_id', 'item_id']);
            $table->index('item_id');
        });

        Schema::create('spells', function (Blueprint $table): void {
            $table->integer('spell_id')->primary();
            $table->integer('icon_id')->nullable();
            $table->integer('cast_ms')->nullable();
            $table->integer('min_range')->nullable();
            $table->integer('max_range')->nullable();
            $table->string('costs', 128)->nullable();
            $table->boolean('passive')->nullable();
            $table->text('description')->nullable();
            $table->timestampTz('updated_at')->useCurrent();
        });

        Schema::create('boss_loot', function (Blueprint $table): void {
            $table->timestampTz('at');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('session_start');
            $table->unsignedInteger('ruleset_id');
            $table->smallInteger('level')->nullable();
            $table->integer('zone_id')->nullable();
            $table->boolean('counted')->default(true);
            $table->integer('encounter_id')->nullable();
            $table->integer('item_id')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->string('who', 64)->nullable();
            $table->string('class_file', 32)->nullable();
            $table->index(['character_id', 'session_start']);
            $table->index(['character_id', 'at']);
            $table->index(['encounter_id', 'item_id']);
            $table->index(['item_id', 'encounter_id']);
        });
        DB::statement('select create_hypertable(?::regclass, by_range(\'at\', ?::interval), create_default_indexes => false)', ['boss_loot', '30 days']);
    }
};
