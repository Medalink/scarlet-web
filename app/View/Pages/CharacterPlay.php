<?php

namespace App\View\Pages;

use App\View\Format;
use Illuminate\Support\Carbon;

/**
 * The play time band: bars of the hours played in the last 21 days and a grid of the hour of the week, from the hours the
 * character played. The page draws them in UTC; resources/js/play.js draws them again in the viewer's time zone from the
 * same hours, so the two must read the same way.
 */
final class CharacterPlay
{
    public const int DAYS = 21;

    private const array WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    private const array WORDS = ['Quiet', 'Light', 'Moderate', 'Busy', 'Busiest'];

    /**
     * @param  list<array{0: int, 1: int}>  $buckets  the hours played, as whole hours since the epoch and seconds, oldest first
     * @return array<string, mixed>|null
     */
    public static function band(array $buckets, Carbon $now): ?array
    {
        if ($buckets === []) {
            return null;
        }

        return [
            'spec' => ['kind' => 'play', 'days' => self::DAYS, 'hours' => array_merge(...$buckets)],
            'bars' => self::bars($buckets, $now->copy()->utc()->startOfDay()),
            'grid' => self::grid($buckets),
        ];
    }

    /**
     * @param  list<array{0: int, 1: int}>  $buckets
     * @return array<string, mixed>
     */
    private static function bars(array $buckets, Carbon $today): array
    {
        $first = $today->copy()->subDays(self::DAYS - 1);
        $seconds = array_fill(0, self::DAYS, 0);
        foreach ($buckets as [$hour, $played]) {
            $index = intdiv($hour, 24) - intdiv($first->getTimestamp(), 86400);
            if ($index >= 0 && $index < self::DAYS) {
                $seconds[$index] += $played;
            }
        }
        $top = max($seconds);
        $busiest = $top > 0 ? (int) array_search($top, $seconds, true) : null;
        $columns = $say = [];
        foreach ($seconds as $i => $value) {
            $day = $first->copy()->addDays($i);
            $line = Format::line($day->format('D j M'), $value > 0 ? ': '.Format::duration($value).' played.' : ': not played.');
            $say[] = $line;
            $columns[] = ['height' => (string) round($value / max(1, $top) * 100, 1), 'class' => $value <= 0 ? 'z' : ($i === $busiest ? 'peak' : ''), 'title' => $line[0].$line[1]];
        }

        return [
            'columns' => $columns,
            'labels' => [$first->format('j M'), $first->copy()->addDays(intdiv(self::DAYS - 1, 2))->format('j M'), $today->format('j M')],
            'max' => $top > 0 ? Format::duration($top) : null,
            'line' => $busiest === null ? Format::line('No play in the last '.self::DAYS.' days') : Format::line('Busiest day', ': '.$first->copy()->addDays($busiest)->format('D j M').', '.Format::duration($top)),
        ];
    }

    /**
     * @param  list<array{0: int, 1: int}>  $buckets
     * @return array<string, mixed>
     */
    private static function grid(array $buckets): array
    {
        $cells = array_fill(0, 168, 0);
        foreach ($buckets as [$hour, $played]) {
            $cells[(intdiv($hour, 24) + 3) % 7 * 24 + $hour % 24] += $played;
        }
        $top = max($cells);
        $busiest = (int) array_search($top, $cells, true);
        $rows = [];
        foreach (self::WEEKDAYS as $d => $name) {
            $row = [];
            for ($h = 0; $h < 24; $h++) {
                $value = $cells[$d * 24 + $h];
                $level = $value <= 0 ? 0 : max(1, (int) ceil($value / $top * 4));
                $row[] = [$level, $name.' '.str_pad((string) $h, 2, '0', STR_PAD_LEFT).':00, '.self::WORDS[$level].($value > 0 ? ', '.Format::duration($value).' played' : '')];
            }
            $rows[$name] = $row;
        }

        return [
            'rows' => $rows,
            'line' => Format::line('Busiest hour', ': '.self::WEEKDAYS[intdiv($busiest, 24)].' '.str_pad((string) ($busiest % 24), 2, '0', STR_PAD_LEFT).':00 UTC'),
        ];
    }
}
