<?php

namespace App\Actions\Account;

use App\Support\Rollup;
use Illuminate\Support\Facades\DB;

class ShowAccountPlayBuckets
{
    /** Weeks of play the weekday and hour grid covers. */
    public const int WEEKS = 12;

    /**
     * The seconds the characters played in each whole hour of the last weeks, summed over the characters.
     *
     * @param  list<int>  $characterIds
     * @return list<array{0: int, 1: int}> whole hours since the epoch and the seconds played in each
     */
    public function handle(array $characterIds): array
    {
        if ($characterIds === []) {
            return [];
        }

        return array_values(DB::table(Rollup::PlayHourlyCharacter->value)->whereIn('character_id', $characterIds)
            ->where('bucket', '>=', now()->subWeeks(self::WEEKS))
            ->selectRaw('(extract(epoch from bucket) / 3600)::bigint as hour, (sum(ms) / 1000.0)::int as seconds')
            ->groupBy('hour')->orderBy('hour')->get()
            ->map(fn (object $row): array => [(int) $row->hour, (int) $row->seconds])->all());
    }
}
