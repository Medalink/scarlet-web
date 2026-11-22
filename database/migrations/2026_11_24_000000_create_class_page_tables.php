<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the class pages read (docs/ROWS.md): the top spells of each damage meter, the time spent in a state, the items
 * that went into and out of the bags with their source, and the counts of sequences. They fill when the scheduler
 * decodes the stored sessions again for decoder version 14 (docs/DESIGN.md, Re-decode), so no upload format changes.
 * It sorts before `null_counters_of_removed_groups`, which reads `FactTable::groupColumns()` and so needs
 * `state_times.fight_ms` and `item_flows.spell_id` on a database built from scratch.
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
            'meter_spells' => function (Blueprint $t): void {
                $t->string('meter', 1);
                $t->integer('spell_id');
                $t->bigInteger('amount');
                $t->integer('fights');
                $t->index(['character_id', 'meter', 'spell_id']);
            },
            'state_times' => function (Blueprint $t): void {
                $t->string('kind', 1);
                $t->integer('ref_id');
                $t->string('name')->nullable();
                $t->string('extra')->nullable();
                $t->integer('starts');
                $t->bigInteger('ms');
                $t->bigInteger('fight_ms')->nullable();
                $t->index(['character_id', 'kind', 'ref_id']);
            },
            'item_flows' => function (Blueprint $t): void {
                $t->integer('item_id');
                $t->smallInteger('dir');
                $t->string('place', 1);
                $t->integer('spell_id')->nullable();
                $t->integer('events');
                $t->bigInteger('quantity');
                $t->index(['character_id', 'item_id']);
            },
            'class_tallies' => function (Blueprint $t): void {
                $t->string('key', 24);
                $t->integer('a')->default(0);
                $t->integer('b')->default(0);
                $t->integer('n');
                $t->bigInteger('sum')->default(0);
                $t->bigInteger('best')->default(0);
                $t->index(['character_id', 'key']);
            },
        ];
    }
};
