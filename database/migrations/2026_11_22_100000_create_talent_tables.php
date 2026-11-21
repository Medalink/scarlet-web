<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The talent tables of docs/ROWS.md. Three are decoded fact hypertables like the others (every row carries
 * `at, character_id, session_start, ruleset_id, level, zone_id, counted`); two hold the client's tree layouts, the
 * same for every player of a client build; the character row gets the latest build's summary.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->facts() as $name => $columns) {
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

        Schema::create('talent_trees', function (Blueprint $table): void {
            $table->integer('tree_id');
            $table->string('build', 32);
            $table->string('layout_hash', 64);
            $table->smallInteger('class_id');
            $table->smallInteger('node_count');
            $table->unsignedInteger('uploads')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['tree_id', 'build', 'layout_hash']);
        });

        Schema::create('talent_nodes', function (Blueprint $table): void {
            $table->integer('tree_id');
            $table->string('build', 32);
            $table->string('layout_hash', 64);
            $table->integer('node_id');
            $table->integer('pos_x')->nullable();
            $table->integer('pos_y')->nullable();
            $table->string('node_type', 24)->nullable();
            $table->smallInteger('max_ranks')->nullable();
            $table->integer('tab_id')->nullable();
            $table->text('entries')->nullable();
            $table->text('edges')->nullable();
            $table->primary(['tree_id', 'build', 'layout_hash', 'node_id']);
        });

        Schema::table('characters', function (Blueprint $table): void {
            $table->integer('spec_tab_id')->nullable();
            $table->smallInteger('talent_points')->nullable();
            $table->string('build_hash', 16)->nullable();
        });
    }

    /** @return array<string, Closure(Blueprint): void> */
    private function facts(): array
    {
        return [
            'talent_changes' => function (Blueprint $t): void {
                $t->smallInteger('spec_group');
                $t->integer('node_id');
                $t->integer('entry_id')->nullable();
                $t->smallInteger('rank');
                $t->smallInteger('old_rank');
                $t->smallInteger('max_ranks')->nullable();
                $t->integer('spell_id')->nullable();
                $t->string('kind', 8);
                $t->smallInteger('point_no')->nullable();
                $t->smallInteger('respec_index')->nullable();
            },
            'talent_snapshots' => function (Blueprint $t): void {
                $t->smallInteger('spec_group');
                $t->smallInteger('groups')->nullable();
                $t->integer('spec_id')->nullable();
                $t->string('spec', 64)->nullable();
                $t->integer('tree_id');
                $t->string('tab_points', 255)->nullable();
                $t->integer('spec_tab_id')->nullable();
                $t->smallInteger('class_points')->nullable();
                $t->smallInteger('spec_points')->nullable();
                $t->bigInteger('config_id')->nullable();
                $t->string('build_hash', 16);
                $t->text('ranks');
                $t->text('loadout')->nullable();
                $t->string('why', 1);
            },
            'talent_respecs' => function (Blueprint $t): void {
                $t->smallInteger('spec_group');
                $t->string('kind', 8);
                $t->unsignedBigInteger('cost_copper')->nullable();
                $t->smallInteger('points_moved');
                $t->string('hash_before', 16);
                $t->string('hash_after', 16)->nullable();
                $t->smallInteger('respec_index');
            },
        ];
    }
};
