<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gathering attempts and the items they gave (docs/ROWS.md), and the profession columns of `crafts`, read from the
 * professions group with the combat, loot, build and movement lines around them. The tables and columns fill when the
 * scheduler decodes the stored sessions again for the new decoder version (docs/DESIGN.md, Re-decode), so no upload
 * format changes. `u, v` are the world position the other placed tables carry. It sorts before
 * `null_counters_of_removed_groups`, which reads `FactTable::groupColumns()` and so needs `gathers` on a database built
 * from scratch. Every column added to `crafts` is nullable, so compressed chunks stay as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->tables() as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns): void {
                $table->timestampTz('at');
                $table->unsignedBigInteger('character_id');
                $table->unsignedBigInteger('session_start');
                $table->unsignedInteger('ruleset_id');
                $table->smallInteger('level')->nullable();
                $table->integer('zone_id')->nullable();
                $table->boolean('counted')->default(true);
                $columns($table);
                $table->index(['character_id', 'session_start']);
                $table->index(['character_id', 'at']);
            });
            DB::statement('select create_hypertable(?::regclass, by_range(\'at\', ?::interval), create_default_indexes => false)', [$name, '7 days']);
        }

        Schema::table('crafts', function (Blueprint $table): void {
            $table->integer('profession_id')->nullable();
            $table->smallInteger('difficulty')->nullable();
            $table->integer('skill')->nullable();
            $table->boolean('skill_up')->nullable();
            $table->boolean('enchant')->nullable();
            $table->double('sitting_gap')->nullable();
        });
    }

    /** @return array<string, Closure(Blueprint): void> */
    private function tables(): array
    {
        return [
            'gathers' => function (Blueprint $t): void {
                $t->string('kind', 4);
                $t->unsignedInteger('spell_id');
                $t->double('seconds')->nullable();
                $t->boolean('looted')->nullable();
                $t->integer('source_id')->nullable();
                $t->boolean('again')->nullable();
                $t->integer('skill')->nullable();
                $t->boolean('skill_up');
                $t->double('gap')->nullable();
                $t->unsignedInteger('lure_spell')->nullable();
                $t->double('lure_age')->nullable();
                $t->integer('pole_item')->nullable();
                $t->boolean('in_combat')->nullable();
                $t->integer('map')->nullable();
                $t->integer('x')->nullable();
                $t->integer('y')->nullable();
                $t->double('u')->nullable();
                $t->double('v')->nullable();
            },
            'gather_items' => function (Blueprint $t): void {
                $t->string('kind', 4);
                $t->integer('source_id')->nullable();
                $t->integer('item_id');
                $t->unsignedInteger('quantity');
                $t->integer('skill')->nullable();
                $t->integer('map')->nullable();
                $t->integer('container_item_id')->nullable();
            },
        ];
    }
};
