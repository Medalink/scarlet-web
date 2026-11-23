<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a recap's `compare` reads (docs/CONTRACTS.md, "Tables and rollups"): for the counted sessions of 10 minutes or more of a ruleset that began in a
 * ten-level bracket, `cuts` holds the 5th to the 95th percentile of the metric in steps of five, 19 numbers, and `n` the sessions they come from.
 * `bracket` is the lowest level of the bracket (30 for levels 30 to 39). Rebuilt daily.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_rates_bracket', function (Blueprint $table): void {
            $table->unsignedInteger('ruleset_id');
            $table->unsignedSmallInteger('bracket');
            $table->string('metric', 16);
            $table->unsignedInteger('n');
            $table->jsonb('cuts');
            $table->timestampTz('updated_at');
            $table->primary(['ruleset_id', 'bracket', 'metric']);
        });
    }
};
