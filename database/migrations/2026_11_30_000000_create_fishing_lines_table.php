<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the companion read from The Fishing Log, and the catches the addon stream recorded from it (docs/DESIGN.md,
 * The Fishing Log): one row per line, its fields by name in `fields`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fishing_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('session_start');
            $table->string('code', 8);
            $table->unsignedBigInteger('at');
            $table->jsonb('fields');
            $table->index(['character_id', 'session_start']);
            $table->index(['code', 'character_id', 'at']);
        });
    }
};
