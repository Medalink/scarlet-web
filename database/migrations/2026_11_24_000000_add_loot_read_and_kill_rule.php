<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a loot window was read for and where it was read, and the loot rule a kill happened under (docs/ROWS.md,
 * decoder version 10). `loot_opens` and `loot_drops` get `read` (loot, skin, herb, ore, fish, bag or other);
 * `loot_opens` also gets `item_rows` and the map position the other placed tables carry (`map`, `x`, `y`, and `u`,
 * `v` from ingest); `kills` get `loot_rule` and `group_size`. The columns fill when the scheduler decodes the stored
 * sessions again for the new decoder version (docs/DESIGN.md, Re-decode), so no upload format changes. Every column is
 * nullable with no default, so rows and compressed chunks written before stay as they are. It sorts before
 * `null_counters_of_removed_groups`, which reads `FactTable::groupColumns()` and so needs these columns on a database
 * built from scratch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loot_opens', function (Blueprint $table): void {
            $table->string('read', 5)->nullable();
            $table->smallInteger('item_rows')->nullable();
            $table->integer('map')->nullable();
            $table->integer('x')->nullable();
            $table->integer('y')->nullable();
            $table->double('u')->nullable();
            $table->double('v')->nullable();
        });

        Schema::table('loot_drops', function (Blueprint $table): void {
            $table->string('read', 5)->nullable();
        });

        Schema::table('kills', function (Blueprint $table): void {
            $table->string('loot_rule', 24)->nullable();
            $table->smallInteger('group_size')->nullable();
        });
    }
};
