<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a recorded point lies on the stitched world (docs/DESIGN.md, Maps): `u` across and `v` down, 0 to 1. Null
 * for a map that has no rectangle in `map_rects` (a dungeon) or while its rectangle has not arrived.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['path_points', 'deaths', 'creature_sightings'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->double('u')->nullable();
                $table->double('v')->nullable();
            });
        }
    }
};
