<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How often the decode of a part failed and why: the budget `ingest:sweep` spends before it
 * leaves a part to the 72 hour give up and the redecode paths. Forward only, and nullable or
 * defaulted, so it expands online.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->text('last_error')->nullable();
        });
    }
};
