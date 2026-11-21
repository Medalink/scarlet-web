<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History the companion read from other addons (docs/DESIGN.md, Imported history). Kept apart from the
 * fact tables on purpose: no rollup, board or public page reads it, only the owner's own pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imported_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('day_start');
            $table->string('kind', 8);
            $table->unsignedBigInteger('at');
            $table->string('source', 24);
            $table->string('via', 64)->nullable();
            $table->unsignedInteger('item_id')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->bigInteger('copper');
            $table->index(['character_id', 'day_start']);
            $table->index(['character_id', 'kind', 'at']);
        });
    }
};
