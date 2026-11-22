<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The lines `HD`, `WH`, `IG`, `BO` and `BM` (Medalink/scarlet-web#17; docs/ROWS.md). The tables fill from a
 * decode of stored parts, so a session decoded before the decoder knew them gets its rows on the next re-decode.
 * One hardcore death notice reaches every uploader online, so `hardcore_deaths` keeps one row per uploader and
 * the pages count each death once when they read.
 */
return new class extends Migration
{
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
            'hardcore_deaths' => ['30 days', function (Blueprint $t): void {
                $t->string('who', 64)->nullable();
                $t->smallInteger('died_level')->nullable();
                $t->string('notice', 400)->nullable();
            }],
            'who_results' => ['30 days', function (Blueprint $t): void {
                $t->string('name', 64)->nullable();
                $t->smallInteger('player_level')->nullable();
                $t->string('class_file', 32)->nullable();
                $t->string('race', 32)->nullable();
                $t->string('zone_name', 96)->nullable();
                $t->string('guild', 96)->nullable();
            }],
            'inspected_gear' => ['30 days', function (Blueprint $t): void {
                $t->string('name', 64)->nullable();
                $t->text('items')->nullable();
            }],
            'battleground_matches' => ['30 days', function (Blueprint $t): void {
                $t->integer('instance_id')->nullable();
                $t->string('zone_name', 96)->nullable();
                $t->boolean('rated')->nullable();
                $t->boolean('arena')->nullable();
                $t->smallInteger('team')->nullable();
                $t->smallInteger('winner')->nullable();
                $t->unsignedInteger('duration')->nullable();
                $t->string('result', 1)->nullable();
                $t->boolean('affects_rating')->nullable();
            }],
            'battleground_scores' => ['30 days', function (Blueprint $t): void {
                $t->timestampTz('match_at');
                $t->integer('instance_id')->nullable();
                $t->string('name', 64)->nullable();
                $t->smallInteger('faction')->nullable();
                $t->string('class_file', 32)->nullable();
                $t->unsignedInteger('killing_blows')->nullable();
                $t->unsignedInteger('honorable_kills')->nullable();
                $t->unsignedInteger('deaths')->nullable();
                $t->unsignedInteger('honor_gained')->nullable();
                $t->unsignedBigInteger('damage_done')->nullable();
                $t->unsignedBigInteger('healing_done')->nullable();
                $t->text('stats')->nullable();
                $t->index(['character_id', 'match_at']);
            }],
        ];
    }
};
