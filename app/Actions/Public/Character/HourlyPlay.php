<?php

namespace App\Actions\Public\Character;

use App\Support\Rollup;
use Illuminate\Support\Facades\DB;

class HourlyPlay
{
    /**
     * The played time of a character from its hourly aggregate: seconds by UTC day, and the hours themselves, each as
     * whole hours since the epoch and the seconds played in it, oldest first.
     *
     * @return array{days: array<string, float>, buckets: list<array{0: int, 1: int}>}
     */
    public function handle(int $characterId): array
    {
        $rows = DB::table(Rollup::PlayHourlyCharacter->value)->where('character_id', $characterId)
            ->selectRaw("to_char(bucket at time zone 'UTC', 'YYYY-MM-DD') as day, (extract(epoch from bucket)::bigint / 3600) as hour_index")
            ->selectRaw('(sum(ms) / 1000.0)::float as seconds')
            ->groupByRaw('1, 2')->orderByRaw('2')->get();
        $days = $buckets = [];
        foreach ($rows as $row) {
            $days[$row->day] = ($days[$row->day] ?? 0.0) + (float) $row->seconds;
            if ((int) round((float) $row->seconds) > 0) {
                $buckets[] = [(int) $row->hour_index, (int) round((float) $row->seconds)];
            }
        }

        return ['days' => $days, 'buckets' => $buckets];
    }
}
