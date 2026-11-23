<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `shared_votes.session_start` is the session a vote row's values came from, so a redecode of an older session never
 * replaces the vote a newer one cast. Null on rows from before it, which any session may replace. Nullable, so the
 * addition rewrites nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_votes', function (Blueprint $table): void {
            $table->bigInteger('session_start')->nullable();
        });
    }
};
