<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requests to /api/v1 per UTC day and caller, for /admin/api (docs/DESIGN.md, Admin). A caller part that is absent is
 * a value (account 0, install and character ''), so the primary key needs no NULLS NOT DISTINCT. No foreign keys: a
 * row outlives a deleted account until the 30-day prune.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_calls', function (Blueprint $table): void {
            $table->date('day');
            $table->string('surface', 8);
            $table->string('endpoint', 96);
            $table->string('address', 45);
            $table->unsignedBigInteger('account_id')->default(0);
            $table->string('install_id', 36)->default('');
            $table->string('character_key', 64)->default('');
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('refused')->default(0);
            $table->timestampTz('first_at');
            $table->timestampTz('last_at');
            $table->primary(['day', 'surface', 'endpoint', 'address', 'account_id', 'install_id', 'character_key']);
        });
    }
};
