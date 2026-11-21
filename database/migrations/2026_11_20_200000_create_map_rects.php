<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where a zone map lies on the stitched world: u0, v0 its top left corner and u1, v1 its bottom right, 0 to 1
        // across and down. Loaded from docs/map-rects.tsv by `php artisan maps:load-rects`.
        Schema::create('map_rects', function (Blueprint $table): void {
            $table->unsignedInteger('ui_map_id')->primary();
            $table->string('name', 64);
            $table->double('u0');
            $table->double('v0');
            $table->double('u1');
            $table->double('v1');
        });
    }
};
