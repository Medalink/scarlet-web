<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The group a character played in and what happened inside it (docs/ROWS.md, decoder version 11): `group_spans` (a
 * stretch in which nothing about the group changed, social), `group_loot` (what the other members received, loot) and
 * `loot_rolls` (one row per player per roll, loot), and `group_at` on `kills` and `loot_opens`, the `at` of the span
 * in force. The rows fill when the scheduler decodes the stored sessions again for the new decoder version (docs/DESIGN.md,
 * Re-decode), so no upload format changes. Every column added to a table that exists is nullable, so rows and compressed
 * chunks written before stay as they are. It sorts before `null_counters_of_removed_groups`, which reads
 * `FactTable::groupColumns()` and so needs these tables and columns on a database built from scratch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kills', function (Blueprint $table): void {
            $table->timestampTz('group_at')->nullable();
        });
        Schema::table('loot_opens', function (Blueprint $table): void {
            $table->timestampTz('group_at')->nullable();
        });

        Schema::create('group_spans', function (Blueprint $table): void {
            $table->timestampTz('at');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('session_start');
            $table->unsignedInteger('ruleset_id');
            $table->smallInteger('level')->nullable();
            $table->integer('zone_id')->nullable();
            $table->boolean('counted')->default(true);
            $table->timestampTz('ended_at');
            $table->smallInteger('size')->nullable();
            $table->boolean('raid')->nullable();
            $table->smallInteger('members_known');
            $table->text('classes')->nullable();
            $table->smallInteger('tanks');
            $table->smallInteger('healers');
            $table->smallInteger('damage');
            $table->smallInteger('no_role');
            $table->smallInteger('level_min')->nullable();
            $table->smallInteger('level_max')->nullable();
            $table->smallInteger('guild_mates');
            $table->smallInteger('friends');
            $table->smallInteger('skinners')->nullable();
            $table->index(['character_id', 'session_start']);
            $table->index(['character_id', 'at']);
        });

        Schema::create('group_loot', function (Blueprint $table): void {
            $table->timestampTz('at');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('session_start');
            $table->unsignedInteger('ruleset_id');
            $table->smallInteger('level')->nullable();
            $table->integer('zone_id')->nullable();
            $table->boolean('counted')->default(true);
            $table->string('kind', 1);
            $table->string('who', 64);
            $table->string('class_file', 32)->nullable();
            $table->integer('item_id');
            $table->unsignedInteger('quantity');
            $table->string('loot_rule', 24);
            $table->smallInteger('threshold')->nullable();
            $table->smallInteger('group_size')->nullable();
            $table->string('source_kind', 1)->nullable();
            $table->integer('source_id')->nullable();
            $table->index(['character_id', 'session_start']);
            $table->index(['character_id', 'at']);
            $table->index(['item_id', 'at']);
        });

        Schema::create('loot_rolls', function (Blueprint $table): void {
            $table->timestampTz('at');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('session_start');
            $table->unsignedInteger('ruleset_id');
            $table->smallInteger('level')->nullable();
            $table->integer('zone_id')->nullable();
            $table->boolean('counted')->default(true);
            $table->integer('roll_no');
            $table->integer('item_id');
            $table->string('who', 64);
            $table->boolean('mine');
            $table->string('class_file', 32)->nullable();
            $table->string('roll_type', 10)->nullable();
            $table->smallInteger('roll')->nullable();
            $table->string('outcome', 10)->nullable();
            $table->integer('decide_ms')->nullable();
            $table->string('loot_rule', 24);
            $table->smallInteger('threshold')->nullable();
            $table->smallInteger('group_size')->nullable();
            $table->index(['character_id', 'session_start']);
            $table->index(['character_id', 'at']);
            $table->index(['item_id', 'at']);
        });

        foreach (['group_spans', 'group_loot', 'loot_rolls'] as $name) {
            DB::statement('select create_hypertable(?::regclass, by_range(\'at\', ?::interval), create_default_indexes => false)', [$name, '7 days']);
        }
    }
};
