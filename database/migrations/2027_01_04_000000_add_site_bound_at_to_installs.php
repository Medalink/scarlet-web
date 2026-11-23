<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `installs.site_bound_at` is when the install first sent a signed request with a site line (docs/API.md,
 * Signing). From then on the server refuses its signatures without one. Null for every install that has
 * not sent one, so a client predating the site line keeps working. Nullable, so the addition rewrites nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installs', function (Blueprint $table): void {
            $table->timestampTz('site_bound_at')->nullable();
        });
    }
};
