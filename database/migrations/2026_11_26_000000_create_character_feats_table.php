<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * The stored feat counts of a character (docs/DESIGN.md "Art and game layer"): one row per feat that
 * applies, its `value` rounded so it can never show a tier it has not reached. The tier is not stored,
 * it is read from the value with `Feat::tier()`, so a threshold change needs no recount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_feats', function (Blueprint $table): void {
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('feat', 32);
            $table->double('value');
            $table->timestampTz('updated_at');
            $table->primary(['character_id', 'feat']);
            $table->index(['feat', 'value']);
        });
    }
};
