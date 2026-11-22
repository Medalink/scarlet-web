<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The lifetime baselines of docs/ROWS.md, read from the `QB`, `KT`, `EB` and `IS` lines: one row per read of the
 * client's lifetime lists and one per instance lock line. The character row keeps the totals of the latest read, which
 * the character page and the lifetime quests board read. The tables fill when the scheduler decodes the stored sessions
 * again for the new decoder version (docs/DESIGN.md, Re-decode), so no upload format changes.
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

        Schema::table('characters', function (Blueprint $table): void {
            $table->unsignedInteger('quests_completed')->nullable();
            $table->unsignedInteger('titles_known')->nullable();
            $table->unsignedInteger('zones_explored')->nullable();
        });
    }

    /** @return array<string, Closure(Blueprint): void> */
    private function tables(): array
    {
        return [
            'quest_baselines' => function (Blueprint $t): void {
                $t->unsignedInteger('total');
                $t->text('ids');
            },
            'title_baselines' => function (Blueprint $t): void {
                $t->unsignedInteger('total');
                $t->text('ids');
            },
            'explored_baselines' => function (Blueprint $t): void {
                $t->unsignedInteger('zones');
                $t->text('maps');
            },
            'instance_locks' => function (Blueprint $t): void {
                $t->string('name')->nullable();
                $t->integer('instance_id')->nullable();
                $t->integer('difficulty_id')->nullable();
                $t->integer('reset_seconds')->nullable();
                $t->boolean('locked');
                $t->boolean('extended');
                $t->smallInteger('raid');
                $t->smallInteger('max_players')->nullable();
                $t->smallInteger('done')->nullable();
                $t->smallInteger('total')->nullable();
                $t->string('why', 1)->nullable();
            },
        ];
    }
};
