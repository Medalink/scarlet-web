<?php

namespace App\Actions\Feats;

use Illuminate\Support\Facades\DB;

/** Drops the stored standings and band of characters whose rows changed under them; the next `RefreshClassStandings` writes what their rows still answer. */
class ForgetClassStandings
{
    /** @param  list<int>  $characterIds */
    public function handle(array $characterIds): void
    {
        DB::table('class_standings')->whereIn('character_id', $characterIds)->delete();
        DB::table('class_bands')->whereIn('character_id', $characterIds)->delete();
    }
}
