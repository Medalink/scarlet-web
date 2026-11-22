<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The feats a character's owner pinned to the top of its page: a list of up to three feat keys, in the order pinned
 * (docs/DESIGN.md, "Art and game layer"). Null when nothing is pinned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table): void {
            $table->jsonb('pinned_feats')->nullable();
        });
    }
};
