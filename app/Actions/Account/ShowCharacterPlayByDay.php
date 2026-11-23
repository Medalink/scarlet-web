<?php

namespace App\Actions\Account;

use App\Support\Rollup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShowCharacterPlayByDay
{
    /** Calendar days the chart covers, ending on the last day with play. */
    public const int DAYS = 30;

    /**
     * Played seconds by UTC day and character, from the hourly aggregate the character page reads, for the last days with play.
     *
     * @param  list<int>  $characterIds
     * @return array<string, array<int, float>> day (Y-m-d) → character id → seconds, oldest first
     */
    public function handle(array $characterIds): array
    {
        $last = DB::table(Rollup::PlayHourlyCharacter->value)->whereIn('character_id', $characterIds)->max('bucket');
        if ($last === null) {
            return [];
        }
        $from = Carbon::parse((string) $last, 'UTC')->startOfDay()->subDays(self::DAYS - 1);
        $rows = DB::table(Rollup::PlayHourlyCharacter->value)->whereIn('character_id', $characterIds)->where('bucket', '>=', $from)
            ->selectRaw("to_char(bucket at time zone 'UTC', 'YYYY-MM-DD') as day, character_id")
            ->selectRaw('(sum(ms) / 1000.0)::float as seconds')
            ->groupBy('day', 'character_id')->orderBy('day')->get();
        $days = [];
        foreach ($rows as $row) {
            $days[$row->day][(int) $row->character_id] = (float) $row->seconds;
        }

        return $days;
    }
}
