<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A session now writes one `spell_casts` row for each spell and level and one `meter_totals` row for each meter,
 * level and place (docs/ROWS.md). The new columns default to 0, and `place` is null on a row written before decoder
 * version 14; the rows fill when the scheduler decodes the stored sessions again (docs/DESIGN.md, Re-decode).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spell_casts', function (Blueprint $table): void {
            $table->integer('fight_casts')->default(0);
            $table->bigInteger('channel_ms')->default(0);
            $table->integer('interrupts')->default(0);
            $table->integer('streak')->default(0);
        });

        Schema::table('meter_totals', function (Blueprint $table): void {
            $table->string('place', 1)->nullable();
            $table->bigInteger('group_total')->default(0);
            $table->bigInteger('group_own')->default(0);
            $table->integer('group_fights')->default(0);
        });
    }
};
