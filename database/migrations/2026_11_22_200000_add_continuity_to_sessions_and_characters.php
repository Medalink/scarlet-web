<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the data rule for characters that start mid-life needs on the rows (docs/DESIGN.md, "Mid-life characters").
 *
 * `sessions.joins` says how a session follows the one before it, from the client's played counter: `first`,
 * `continuous`, `gap` (play Scarlet did not record sits between them, `unrecorded_ms` long) or `unknown`.
 * `level_ups.ding` is false for an `L` line that did not raise a known level, so it is not a level-up Scarlet saw.
 * `characters.played_known` is true when `played_seconds` is the client's own total and not the sum of the
 * recorded sessions, `recorded_seconds` is that sum, `first_level` the level at the first recorded session and
 * `whole_life` true when Scarlet saw the character from creation with no unrecorded play since.
 *
 * Existing rows keep the defaults until their sessions are decoded again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->string('joins', 12)->nullable();
            $table->unsignedBigInteger('unrecorded_ms')->nullable();
        });
        Schema::table('level_ups', function (Blueprint $table): void {
            $table->boolean('ding')->default(true);
        });
        Schema::table('characters', function (Blueprint $table): void {
            $table->boolean('played_known')->default(false);
            $table->unsignedBigInteger('recorded_seconds')->default(0);
            $table->unsignedSmallInteger('first_level')->nullable();
            $table->boolean('whole_life')->default(false);
        });
    }
};
