<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The votes behind the shared reference rows (docs/DESIGN.md, Decoded facts). `shared_votes` holds one row per install
 * and row with the latest value of each field the install read, so the value a row serves is the one more voters hold
 * than the served one; a row that had no votes when the first one came is backed by a row with no install. The talent
 * layouts of `talent_trees` are ranked by the installs that sent them, one row per install and layout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_votes', function (Blueprint $table): void {
            $table->string('kind', 16);
            $table->integer('row_id');
            $table->uuid('install_id')->nullable();
            $table->jsonb('fields');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->unique(['kind', 'row_id', 'install_id'])->nullsNotDistinct();
            $table->foreign('install_id')->references('id')->on('installs')->cascadeOnDelete();
            $table->index(['install_id', 'updated_at']);
        });

        Schema::create('talent_layout_votes', function (Blueprint $table): void {
            $table->integer('tree_id');
            $table->string('build', 32);
            $table->string('layout_hash', 64);
            $table->uuid('install_id');
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['tree_id', 'build', 'layout_hash', 'install_id']);
            $table->foreign('install_id')->references('id')->on('installs')->cascadeOnDelete();
            $table->index('install_id');
        });
    }
};
