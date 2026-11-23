<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The board `feat-score` orders public characters by `characters.feat_score`. Built concurrently, so a running site keeps writing characters. */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('create index concurrently if not exists characters_feat_score_index on characters (feat_score)');
    }
};
