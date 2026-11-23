<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `installs.client` keeps the User-Agent an install last signed a request with (`scarlet-app/1.0.4 (windows)`), for
 * /admin/clients; null until the install's next request. `api_calls.limited` counts the requests answered 429 apart
 * from the other refusals, for /admin/limits; rows written before read 0 (docs/DESIGN.md, Admin). Both additions are
 * nullable or carry a constant default, so neither rewrites its table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installs', function (Blueprint $table): void {
            $table->string('client', 96)->nullable();
        });

        Schema::table('api_calls', function (Blueprint $table): void {
            $table->unsignedInteger('limited')->default(0);
        });
    }
};
