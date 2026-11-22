<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What happened while a Camp Benefits aura was held (decoder version 7, docs/ROWS.md): experience gained and
 * deaths, and on every row how long the buff was given for. Null on the nine benefit buff rows, and `xp` on a session without an `xp` part. It sorts before
 * `null_counters_of_removed_groups`, which reads `FactTable::groupColumns()` and so needs `camp_benefits.xp` on a
 * database built from scratch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camp_benefits', function (Blueprint $table): void {
            $table->unsignedInteger('duration')->nullable();
            $table->unsignedBigInteger('xp')->nullable();
            $table->unsignedInteger('deaths')->nullable();
        });
    }
};
