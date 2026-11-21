<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client's played counter (milliseconds) at the start and at the end of a session, from its `TP` reads: a read's
 * total less the time since the session began, or plus the time since the read. The client counts through a reload,
 * which `played_ms` (start to end) does not, so the character's played time and the time to reach each level are read
 * from these. Null for a session with no `TP` read, and for sessions decoded before the columns existed until they
 * are decoded again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->unsignedBigInteger('counter_start_ms')->nullable();
            $table->unsignedBigInteger('counter_end_ms')->nullable();
        });
    }
};
