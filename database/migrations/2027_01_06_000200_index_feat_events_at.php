<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The nightly prune and the hub's week of events read `feat_events` by `at` alone. Built concurrently, so a running site keeps writing events. */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('create index concurrently if not exists feat_events_at_index on feat_events (at)');
    }
};
