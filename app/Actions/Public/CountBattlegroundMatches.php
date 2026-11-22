<?php

namespace App\Actions\Public;

use Illuminate\Support\Facades\DB;

class CountBattlegroundMatches
{
    /** The matches of a character that have scoreboard rows. */
    public function handle(int $characterId): int
    {
        return (int) DB::table('battleground_scores')->where('character_id', $characterId)->distinct()->count('match_at');
    }
}
