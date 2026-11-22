<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The six figures of a class page that it compares with other characters of the class (docs/DESIGN.md, Class pages):
 * one row per character and figure, rewritten whenever the character's feats are counted. The page reads a place among
 * the public characters of the class in the level bracket, so a row carries the class and the level it was counted at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_standings', function (Blueprint $table): void {
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('class', 16);
            $table->smallInteger('level');
            $table->unsignedInteger('ruleset_id')->nullable();
            $table->string('figure', 32);
            $table->double('value');
            $table->timestampTz('counted_at');
            $table->primary(['character_id', 'figure']);
            $table->index(['class', 'figure', 'level']);
            $table->index(['figure', 'level']);
        });
    }
};
