<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** TimescaleDB loads into the session, so this runs outside the migration transaction. */
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('create extension if not exists timescaledb');
        DB::statement('create extension if not exists timescaledb_toolkit');
        DB::statement('create extension if not exists pg_trgm');
    }
};
