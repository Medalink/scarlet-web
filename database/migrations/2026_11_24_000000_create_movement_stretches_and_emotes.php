<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Movement stretches (`W`) and emotes (`EM`): the last two undecoded line codes that the art and game
 * layer reads (docs/DESIGN.md "Row tables"). The tables fill from a re-decode of stored parts, so no
 * upload format changes. `jumpAt` is not kept, only the jump count.
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
            'movement_stretches' => ['1 day', function (Blueprint $t): void {
                $t->string('mode', 1)->nullable();
                $t->unsignedInteger('yards')->nullable();
                $t->unsignedInteger('seconds')->nullable();
                $t->smallInteger('jumps')->nullable();
            }],
            'emotes' => ['7 days', function (Blueprint $t): void {
                $t->string('token', 32)->nullable();
            }],
        ];
    }
};
