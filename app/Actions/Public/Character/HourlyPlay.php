<?php

namespace App\Actions\Public\Character;

use App\Support\Rollup;
use Illuminate\Support\Facades\DB;

class HourlyPlay
{
    /**
     * The played time of a character from its hourly aggregate, in UTC, as seconds by day and as seconds by weekday
     * (0 is Sunday) and hour of the day.
     *
     * @return array{days: array<string, float>, hours: list<array{weekday: int, hour: int, seconds: float}>}
     */
    public function handle(int $characterId): array
    {
        $rows = DB::table(Rollup::PlayHourlyCharacter->value)->where('character_id', $characterId)
            ->selectRaw("to_char(bucket at time zone 'UTC', 'YYYY-MM-DD') as day, extract(dow from bucket at time zone 'UTC')::int as weekday, extract(hour from bucket at time zone 'UTC')::int as hour")
            ->selectRaw('(sum(ms) / 1000.0)::float as seconds')
            ->groupByRaw('1, 2, 3')->orderByRaw('1, 3')->get();
        $days = $cells = [];
        foreach ($rows as $row) {
            $days[$row->day] = ($days[$row->day] ?? 0.0) + (float) $row->seconds;
            $cells[$row->weekday * 24 + $row->hour] = ($cells[$row->weekday * 24 + $row->hour] ?? 0.0) + (float) $row->seconds;
        }
        $hours = [];
        foreach ($cells as $key => $seconds) {
            $hours[] = ['weekday' => intdiv($key, 24), 'hour' => $key % 24, 'seconds' => $seconds];
        }

        return ['days' => $days, 'hours' => $hours];
    }
}
