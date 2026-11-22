<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A session now writes one `spell_casts` row for each spell and level and one `meter_totals` row for each meter,
 * level and place (docs/ROWS.md). Every column added to a table that exists is nullable with no default, so rows and
 * compressed chunks written before stay as they are. A row stored before decoder version 14 reads null in them, which
 * a reader treats as 0 (and `place` as unknown); the rows fill when the scheduler decodes the stored sessions again
 * (docs/DESIGN.md, Re-decode).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spell_casts', function (Blueprint $table): void {
            $table->integer('fight_casts')->nullable();
            $table->bigInteger('channel_ms')->nullable();
            $table->integer('interrupts')->nullable();
            $table->integer('streak')->nullable();
        });

        Schema::table('meter_totals', function (Blueprint $table): void {
            $table->string('place', 1)->nullable();
            $table->bigInteger('group_total')->nullable();
            $table->bigInteger('group_own')->nullable();
            $table->integer('group_fights')->nullable();
        });
    }
};
