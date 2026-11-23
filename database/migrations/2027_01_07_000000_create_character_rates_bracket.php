<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the compare strips of a character page read (docs/CONTRACTS.md, "Tables and rollups"): for the characters of a class that played in a ten-level
 * bracket, `cuts` holds the 5th to the 95th percentile of the metric in steps of five, 19 numbers, and `n` the characters they come from.
 * `bracket` is the lowest level of the bracket (40 for levels 40 to 49). Rebuilt daily.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_rates_bracket', function (Blueprint $table): void {
            $table->unsignedInteger('ruleset_id');
            $table->string('class', 32);
            $table->unsignedSmallInteger('bracket');
            $table->string('metric', 16);
            $table->unsignedInteger('n');
            $table->jsonb('cuts');
            $table->timestampTz('updated_at');
            $table->primary(['ruleset_id', 'class', 'bracket', 'metric']);
        });
    }
};
