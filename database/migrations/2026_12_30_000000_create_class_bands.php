<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The band of class figures on `/c/{name}` (docs/DESIGN.md, Class pages), stored with the class standings so the
 * character page reads one row instead of building the class page: one row per character, rewritten whenever its
 * standings are. A character with no row has no band.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_bands', function (Blueprint $table): void {
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('class', 16);
            $table->jsonb('icon')->nullable();
            $table->jsonb('figures');
            $table->timestampTz('counted_at');
            $table->primary('character_id');
        });
    }
};
