<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the session pages of a public character are public (`/c/{name}/sessions/{start}`). On for every character,
 * because the sessions group of the upload is the core group and every upload carries it; the owner turns it off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table): void {
            $table->boolean('show_sessions')->default(true);
        });
    }
};
