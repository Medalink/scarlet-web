<?php

namespace App\View\Pages;

use App\View\Format;

/** The rates /stats sets beside one character's: how fast kills, quests and deaths come in a recorded hour. */
final class StatsCompare
{
    private const array KEYS = ['kills' => 'Kills an hour', 'quests' => 'Quests an hour', 'deaths' => 'Deaths an hour'];

    /**
     * @param  array<string, int|float|null>  $totals  the rollup totals of the page, with played_seconds
     * @return list<array{label: string, value: string}>
     */
    public static function everyone(array $totals): array
    {
        $seconds = (float) ($totals['played_seconds'] ?? 0);

        return $seconds <= 0 ? [] : self::rates(array_map(fn (string $key): float => (float) ($totals[$key] ?? 0), array_keys(self::KEYS)), $seconds);
    }

    /**
     * @param  array{kills: int, deaths: int, quests: int}  $sessions
     * @return list<array{label: string, value: string}>
     */
    public static function character(array $sessions, int $recordedSeconds): array
    {
        return $recordedSeconds <= 0 ? [] : self::rates([(float) $sessions['kills'], (float) $sessions['quests'], (float) $sessions['deaths']], (float) $recordedSeconds);
    }

    /**
     * @param  list<float>  $counts  in the order of KEYS
     * @return list<array{label: string, value: string}>
     */
    private static function rates(array $counts, float $seconds): array
    {
        $rows = [];
        foreach (array_values(self::KEYS) as $i => $label) {
            $rows[] = ['label' => $label, 'value' => Format::number($counts[$i] / ($seconds / 3600), 2)];
        }

        return $rows;
    }
}
