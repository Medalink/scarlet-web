<?php

namespace App\Actions\Public;

use Illuminate\Support\Facades\DB;

class HardcoreDeaths
{
    /**
     * Hardcore deaths, each counted once. The server's notice reaches every uploader online, so `hardcore_deaths`
     * holds a row for each of them; rows of the same name (the notice text when it names nobody), level and minute
     * are one death. Rows of sessions flagged as implausible are left out. The query is raw SQL because
     * DISTINCT ON, which picks one row of each death, has no query builder form.
     *
     * @return array{levels: array<int, int>, days: array<string, int>, unknown_level: int, total: int} deaths by the level the character died at, by UTC day (Y-m-d), without a level, and all
     */
    public function handle(): array
    {
        $deaths = DB::table('hardcore_deaths')
            ->where('counted', true)
            ->whereRaw('coalesce(who, notice) is not null')
            ->selectRaw("distinct on (coalesce(who, notice), died_level, date_trunc('minute', at)) died_level, at")
            ->orderByRaw("coalesce(who, notice), died_level, date_trunc('minute', at), at");

        $rows = DB::query()->fromSub($deaths, 'd')
            ->selectRaw("died_level, (at at time zone 'UTC')::date as day, count(*) as deaths")
            ->groupBy('died_level')->groupByRaw("(at at time zone 'UTC')::date")
            ->get();

        $levels = $days = [];
        $unknown = $total = 0;
        foreach ($rows as $row) {
            $count = (int) $row->deaths;
            $total += $count;
            $days[(string) $row->day] = ($days[(string) $row->day] ?? 0) + $count;
            if ($row->died_level === null) {
                $unknown += $count;

                continue;
            }
            $levels[(int) $row->died_level] = ($levels[(int) $row->died_level] ?? 0) + $count;
        }
        ksort($levels);
        ksort($days);

        return ['levels' => $levels, 'days' => $days, 'unknown_level' => $unknown, 'total' => $total];
    }
}
