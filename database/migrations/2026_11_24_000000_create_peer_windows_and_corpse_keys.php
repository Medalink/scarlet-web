<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The corpse key and the windows other members read (docs/ROWS.md, decoder version 12). `kills`, `loot_opens` and
 * `loot_drops` get `corpse_key`, the 10 hex digits the addon appends to `K`, `LO` and `LW`; `peer_opens` and
 * `peer_drops` hold the loot windows of group members who run Scarlet (`LW`, loot). The columns and tables fill when the
 * scheduler decodes the stored sessions again for the new decoder version (docs/DESIGN.md, Re-decode), so no upload
 * format changes. Every column added to a table that exists is nullable, so rows and compressed chunks written before
 * stay as they are. It sorts before `null_counters_of_removed_groups`, which reads `FactTable::groupColumns()` and so
 * needs these tables and columns on a database built from scratch.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['kills', 'loot_opens', 'loot_drops'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('corpse_key', 10)->nullable();
            });
        }

        Schema::create('peer_opens', function (Blueprint $table): void {
            $table->timestampTz('at');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('session_start');
            $table->unsignedInteger('ruleset_id');
            $table->smallInteger('level')->nullable();
            $table->integer('zone_id')->nullable();
            $table->boolean('counted')->default(true);
            $table->string('source_kind', 1);
            $table->integer('source_id')->nullable();
            $table->boolean('first_read')->nullable();
            $table->smallInteger('slots')->nullable();
            $table->boolean('has_items');
            $table->string('loot_rule', 24);
            $table->smallInteger('threshold')->nullable();
            $table->smallInteger('group_size')->nullable();
            $table->string('read', 5)->nullable();
            $table->smallInteger('item_rows')->nullable();
            $table->timestampTz('group_at')->nullable();
            $table->string('who', 64);
            $table->integer('late_s')->nullable();
            $table->string('corpse_key', 10)->nullable();
            $table->index(['character_id', 'session_start']);
            $table->index(['character_id', 'at']);
        });

        Schema::create('peer_drops', function (Blueprint $table): void {
            $table->timestampTz('at');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('session_start');
            $table->unsignedInteger('ruleset_id');
            $table->smallInteger('level')->nullable();
            $table->integer('zone_id')->nullable();
            $table->boolean('counted')->default(true);
            $table->string('source_kind', 1);
            $table->integer('source_id')->nullable();
            $table->boolean('first_read')->nullable();
            $table->integer('item_id');
            $table->unsignedInteger('quantity');
            $table->string('loot_rule', 24);
            $table->smallInteger('threshold')->nullable();
            $table->smallInteger('group_size')->nullable();
            $table->string('read', 5)->nullable();
            $table->string('who', 64);
            $table->integer('late_s')->nullable();
            $table->string('corpse_key', 10)->nullable();
            $table->index(['character_id', 'session_start']);
            $table->index(['character_id', 'at']);
            $table->index(['item_id', 'at']);
        });

        foreach (['peer_opens', 'peer_drops'] as $name) {
            DB::statement('select create_hypertable(?::regclass, by_range(\'at\', ?::interval), create_default_indexes => false)', [$name, '7 days']);
        }
    }
};
