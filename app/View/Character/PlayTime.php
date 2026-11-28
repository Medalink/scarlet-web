<?php

namespace App\View\Character;

use App\Models\Character;
use App\View\Charts;
use App\View\Dates;
use App\View\Format;
use Illuminate\Support\Carbon;

/** The `#time` section of /c/{name}: when the character plays, and where its recorded time went. */
final class PlayTime
{
    private const array WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /**
     * @param  list<array{0: int, 1: int}>  $buckets  whole hours since the epoch and the seconds played in each
     * @param  array{moving: array<string, int>, combat: int, group: int}  $splits
     * @param  list<array<string, mixed>>  $everyone  PlayTimeByWeekday: weekday (0 is Sunday), hour, seconds
     * @return array<string, mixed>|null
     */
    public static function data(array $buckets, array $splits, Character $character, array $everyone, string $first): ?array
    {
        $recorded = (int) $character->recorded_seconds;
        if ($buckets === [] && $recorded === 0) {
            return null;
        }
        $grid = self::grid($buckets);

        return [
            'week' => $grid['spec'],
            'weekLine' => $grid['line'],
            'say' => self::say($grid['busiest'], $everyone),
            'moving' => self::moving($splits['moving'], $recorded),
            'combat' => self::either($splits['combat'], $recorded, 'In combat', 'Out of combat'),
            'group' => self::either($splits['group'], $recorded, 'In a group', 'Solo'),
            'first' => $first,
        ];
    }

    /**
     * @param  list<array{0: int, 1: int}>  $buckets
     * @return array{spec: array<string, mixed>, line: array{0: string, 1: string}|null, busiest: int|null}
     */
    public static function grid(array $buckets): array
    {
        $seconds = array_fill(0, 168, 0.0);
        foreach ($buckets as [$hour, $played]) {
            $at = Carbon::createFromTimestampUTC($hour * 3600);
            $seconds[(($at->dayOfWeekIso - 1) * 24) + $at->hour] += $played;
        }
        $top = max($seconds);
        $busiest = $top > 0 ? (int) array_search($top, $seconds, true) : null;

        return [
            'spec' => Charts::weekCells($seconds, $busiest, 'hours-out'),
            'line' => $busiest === null ? null : Format::line('Busiest hour', ': '.self::WEEKDAYS[intdiv($busiest, 24)].' '.Dates::hour($busiest % 24).' UTC'),
            'busiest' => $busiest,
        ];
    }

    /** @param  list<array<string, mixed>>  $everyone */
    private static function say(?int $busiest, array $everyone): ?string
    {
        if ($busiest === null) {
            return null;
        }
        $top = null;
        foreach ($everyone as $row) {
            $top = $top === null || $row['seconds'] > $top['seconds'] ? $row : $top;
        }

        return $top === null ? null : 'Everyone\'s busiest hour is <b>'.Dates::weekHour((((int) $top['weekday'] + 6) % 7) * 24 + (int) $top['hour']).'</b>.';
    }

    /**
     * @param  array<string, int>  $moving  seconds by movement mode, and `taxi`
     * @return list<array{0: string, 1: int, 2: string, 3: string}>
     */
    private static function moving(array $moving, int $recorded): array
    {
        $parts = [
            ['Swimming', $moving['s'] ?? 0, '--m-swim'],
            ['On foot', ($moving['f'] ?? 0) + ($moving['w'] ?? 0), '--m-foot'],
            ['Mounted', ($moving['m'] ?? 0) + ($moving['a'] ?? 0), '--m-mount'],
            ['Flight paths', $moving['taxi'] ?? 0, '--m-flight'],
        ];
        $still = max(0, $recorded - array_sum(array_column($parts, 1)));
        $parts[] = ['Standing still', $still, '--m-still'];

        return array_values(array_map(fn (array $part): array => [$part[0], $part[1], $part[2], Format::hours($part[1], false)], array_filter($parts, fn (array $part): bool => $part[1] > 0)));
    }

    /** @return list<array{0: string, 1: int, 2: string, 3: string}> */
    private static function either(int $seconds, int $recorded, string $in, string $out): array
    {
        if ($seconds <= 0) {
            return [];
        }
        $rest = max(0, $recorded - $seconds);

        return [[$in, $seconds, '--accent', Format::hours($seconds, false)], [$out, $rest, '--m-still', Format::hours($rest, false)]];
    }
}
