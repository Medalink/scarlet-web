<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `installs.origin` is the host the install registered on, the one side of the site line its signed
 * requests name (docs/DESIGN.md, Security). Null for every install registered before the column, and
 * filled from the first signed request that names its site (docs/API.md, Signing). Nullable, so the
 * addition rewrites nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installs', function (Blueprint $table): void {
            $table->string('origin')->nullable();
        });
    }
};
