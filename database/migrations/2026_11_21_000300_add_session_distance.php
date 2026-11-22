<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How far a session walked, in world widths: the length of its recorded path on the world map (docs/DESIGN.md, Maps),
 * measured when the session is stored and by `maps:place-points` for sessions stored before. Null until it is
 * measured or once its movement is removed, 0 when no step counts. The most-distance board adds it up per character.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->double('distance')->nullable();
        });
    }
};
