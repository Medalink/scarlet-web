<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The install behind each name vote, so one uploader's session count no longer decides a name's rank
 * (docs/DESIGN.md, Names), and the row an `items` or `zones` upsert displaces, so the last writer
 * cannot erase the values the pages showed before it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('name_votes', function (Blueprint $table): void {
            $table->string('kind', 16);
            $table->integer('id');
            $table->string('name', 255);
            $table->uuid('install_id');
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['kind', 'id', 'name', 'install_id']);
            $table->foreign('install_id')->references('id')->on('installs')->cascadeOnDelete();
            $table->index(['install_id', 'created_at']);
        });

        foreach (['items', 'zones'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->jsonb('previous')->nullable();
            });
        }
    }
};
