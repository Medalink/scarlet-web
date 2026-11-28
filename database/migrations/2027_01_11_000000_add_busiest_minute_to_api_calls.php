<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `api_calls.busiest_minute` keeps the most requests the caller's key had counted in one rate-limit window when one of
 * the day's requests was answered, for the accounts list of /admin/api (docs/DESIGN.md, Admin). Rows written before
 * read 0. The addition carries a constant default, so the table is not rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_calls', function (Blueprint $table): void {
            $table->unsignedInteger('busiest_minute')->default(0);
        });
    }
};
