<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What players did with a creature, item, quest or zone, one number each, so search can rank a tier by it
 * (docs/DESIGN.md, Search). `RefillSearchScores` replaces the rows every hour from the rollups. A character has no
 * row: it ranks by the time played on its own row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_scores', function (Blueprint $table): void {
            $table->string('kind', 16);
            $table->bigInteger('id');
            $table->double('score');
            $table->primary(['kind', 'id']);
        });
    }
};
