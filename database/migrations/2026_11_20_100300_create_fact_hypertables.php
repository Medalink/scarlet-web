<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The decoded fact tables of docs/ROWS.md as TimescaleDB hypertables on `at`. Every row carries
 * `at, character_id, session_start, ruleset_id, level, zone_id` and `counted` (false for a session with
 * a plausibility flag). No foreign keys: a hypertable row is deleted by character and session, and the
 * check per inserted row would cost more than the invariant is worth.
 */
return new class extends Migration
{
    /** Chunk interval of the tables that hold most rows. */
    private const string DENSE = '1 day';

    private const string SPARSE = '7 days';

    public function up(): void
    {
        foreach ($this->tables() as $name => [$interval, $columns]) {
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
            DB::statement('select create_hypertable(?::regclass, by_range(\'at\', ?::interval), create_default_indexes => false)', [$name, $interval]);
        }
    }

    /** @return array<string, array{0: string, 1: Closure(Blueprint): void}> */
    private function tables(): array
    {
        return [
            'play_spans' => [self::SPARSE, function (Blueprint $t): void {
                $t->unsignedInteger('ms');
                $t->unsignedInteger('afk_ms')->nullable();
                $t->string('zone_name', 128)->nullable();
                $t->string('instance_type', 16)->nullable();
                $t->integer('instance_id')->nullable();
            }],
            'level_ups' => [self::SPARSE, function (Blueprint $t): void {
                $t->smallInteger('reached');
                $t->unsignedInteger('since_ms');
                $t->unsignedInteger('tp_level_s')->nullable();
            }],
            'xp_gains' => [self::SPARSE, function (Blueprint $t): void {
                $t->unsignedInteger('amount');
                $t->string('source', 1);
                $t->integer('source_id')->nullable();
                $t->unsignedInteger('xp')->nullable();
                $t->unsignedInteger('xp_max')->nullable();
            }],
            'kills' => [self::SPARSE, function (Blueprint $t): void {
                $t->integer('npc_id')->nullable();
                $t->boolean('player');
                $t->smallInteger('credit')->nullable();
            }],
            'deaths' => [self::SPARSE, function (Blueprint $t): void {
                $t->integer('map_id')->nullable();
                $t->integer('x')->nullable();
                $t->integer('y')->nullable();
                $t->string('killer_kind', 1)->nullable();
                $t->string('killer', 128)->nullable();
                $t->smallInteger('killer_level')->nullable();
                $t->string('killer_class', 32)->nullable();
                $t->string('killer_race', 32)->nullable();
                $t->string('killer_guild', 64)->nullable();
                $t->integer('spell_id')->nullable();
                $t->bigInteger('damage')->nullable();
                $t->bigInteger('overkill')->nullable();
                $t->integer('hits')->nullable();
            }],
            'dungeon_runs' => [self::SPARSE, function (Blueprint $t): void {
                $t->smallInteger('run_index');
                $t->integer('instance_id');
                $t->string('name', 128)->nullable();
                $t->string('instance_type', 16)->nullable();
                $t->integer('difficulty_id')->nullable();
                $t->timestampTz('left_at')->nullable();
                $t->boolean('left_by_session_end');
                $t->unsignedInteger('inside_ms');
                $t->smallInteger('entries');
                $t->smallInteger('bosses');
                $t->smallInteger('deaths');
                $t->smallInteger('group_size')->nullable();
                // The first run of a session carries on the last run of the session before (ROWS.md, joining sessions).
                $t->boolean('continues_previous')->default(false);
            }],
            'quest_events' => [self::SPARSE, function (Blueprint $t): void {
                $t->integer('quest_id')->nullable();
                $t->string('event', 12);
                $t->unsignedInteger('xp')->nullable();
                $t->unsignedBigInteger('copper')->nullable();
            }],
            'money_changes' => [self::SPARSE, function (Blueprint $t): void {
                $t->string('src', 1);
                $t->bigInteger('copper');
                $t->bigInteger('transfer');
                $t->unsignedBigInteger('earned');
                $t->unsignedBigInteger('spent');
                $t->bigInteger('balance')->nullable();
            }],
            'loot_opens' => [self::SPARSE, function (Blueprint $t): void {
                $t->string('source_kind', 1);
                $t->integer('source_id')->nullable();
                $t->boolean('first_read')->nullable();
                $t->smallInteger('slots')->nullable();
                $t->boolean('has_items');
                $t->string('loot_rule', 24);
                $t->smallInteger('threshold')->nullable();
                $t->smallInteger('group_size')->nullable();
            }],
            'loot_drops' => [self::SPARSE, function (Blueprint $t): void {
                $t->string('via', 8);
                $t->string('source_kind', 1)->nullable();
                $t->integer('source_id')->nullable();
                $t->boolean('first_read')->nullable();
                $t->integer('item_id');
                $t->unsignedInteger('quantity');
                $t->string('loot_rule', 24);
                $t->smallInteger('threshold')->nullable();
                $t->smallInteger('group_size')->nullable();
            }],
            'skill_ups' => [self::SPARSE, function (Blueprint $t): void {
                $t->string('kind', 2);
                $t->string('skill', 64)->nullable();
                $t->integer('skill_id')->nullable();
                $t->integer('skill_rank')->nullable();
                $t->integer('max_rank')->nullable();
                $t->boolean('baseline')->nullable();
            }],
            'crafts' => [self::SPARSE, function (Blueprint $t): void {
                $t->integer('item_id')->nullable();
                $t->unsignedInteger('quantity')->nullable();
                $t->integer('crit')->nullable();
                $t->integer('multicraft')->nullable();
                $t->integer('recipe_id')->nullable();
                $t->integer('quality')->nullable();
            }],
            'path_points' => [self::DENSE, function (Blueprint $t): void {
                $t->integer('map')->nullable();
                $t->integer('x')->nullable();
                $t->integer('y')->nullable();
                $t->integer('facing')->nullable();
            }],
            'creature_sightings' => [self::DENSE, function (Blueprint $t): void {
                $t->integer('npc_id')->nullable();
                $t->smallInteger('npc_level')->nullable();
                $t->string('classification', 24)->nullable();
                $t->string('creature_type', 32)->nullable();
                $t->string('family', 32)->nullable();
                $t->smallInteger('reaction')->nullable();
                $t->integer('map')->nullable();
                $t->smallInteger('cell_x')->nullable();
                $t->smallInteger('cell_y')->nullable();
            }],
            // Not a decoder table: one row per character and level, rebuilt by ingest (DESIGN.md "Rollups").
            'character_levels' => [self::SPARSE, function (Blueprint $t): void {
                $t->smallInteger('reached');
                $t->unsignedInteger('seconds');
                $t->unsignedInteger('before_seconds');
                $t->boolean('completed');
            }],
        ];
    }
};
