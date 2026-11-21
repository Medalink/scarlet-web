<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Character pages are public by default (owner, 2026-11-21): ClaimCharacter now creates characters public. Every
 * character stored so far had the old default, since no owner had chosen private yet, so they all become public once.
 * Gold, trades and other players' names stay hidden behind their own switches.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('characters')->where('is_public', false)->update(['is_public' => true]);
    }
};
