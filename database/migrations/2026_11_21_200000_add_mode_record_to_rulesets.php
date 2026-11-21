<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The game mode record the client reported at login (`RU` record: C_GameRules.GetCurrentGameModeRecordID). It is part
 * of a ruleset's identity from now on, so once an in game check shows which records are Normal, PvP, Roleplay and
 * Roleplay PvP, `RulesetKind::of()` can name them. Null for every ruleset stored before and for sessions that
 * do not report it; their keys do not change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rulesets', function (Blueprint $table): void {
            $table->integer('mode_record')->nullable();
        });
    }
};
