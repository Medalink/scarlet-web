<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Campsite stays and the benefits held with them (docs/ROWS.md), read from the `A` aura and `C` cast lines of the
 * combat group. The tables fill when the scheduler decodes the stored sessions again for the new decoder version
 * (docs/DESIGN.md, Re-decode), so no upload format changes. `u, v` are the world position the other placed tables carry. It sorts before `null_counters_of_removed_groups`,
 * which reads `FactTable::groupColumns()` and so needs `camp_stays` on a database built from scratch.
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
    }

    /** @return array<string, Closure(Blueprint): void> */
    private function tables(): array
    {
        return [
            'camp_stays' => function (Blueprint $t): void {
                $t->unsignedInteger('seconds');
                $t->boolean('carried');
                $t->string('ended', 1);
                $t->unsignedInteger('afk_seconds')->nullable();
                $t->unsignedInteger('sat_seconds');
                $t->boolean('benefits');
                $t->unsignedInteger('tent_seconds');
                $t->unsignedInteger('campfire_spell_id')->nullable();
                $t->boolean('placed_tent');
                $t->boolean('placed_chair');
                $t->unsignedInteger('crafts')->nullable();
                $t->boolean('died_after');
                $t->integer('map')->nullable();
                $t->integer('x')->nullable();
                $t->integer('y')->nullable();
                $t->double('u')->nullable();
                $t->double('v')->nullable();
            },
            'camp_benefits' => function (Blueprint $t): void {
                $t->unsignedInteger('spell_id');
                $t->unsignedInteger('seconds');
                $t->boolean('carried');
                $t->boolean('lost_to_death');
            },
        ];
    }
};
