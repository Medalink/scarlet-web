<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** The time range the rollups must look at again after rows of some characters changed. */
final class RefreshWindow
{
    /**
     * @param  list<int>  $characterIds
     * @return array{from: int, until: int}|null epoch seconds, null when the characters have no session
     */
    public static function ofCharacters(array $characterIds): ?array
    {
        $row = DB::table('sessions')
            ->whereIn('character_id', $characterIds)
            ->selectRaw('extract(epoch from min(start_at)) as first')
            ->selectRaw('extract(epoch from max(end_at)) as last')
            ->first();

        if ($row === null || $row->first === null) {
            return null;
        }

        return ['from' => (int) floor((float) $row->first), 'until' => (int) ceil((float) $row->last)];
    }
}
