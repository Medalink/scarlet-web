<?php

namespace App\View;

/**
 * Builds the specs resources/js/charts.js draws. A page hands in numbers and gets back plain arrays for the
 * chart components; every label is formatted here, so the script never formats a number. Pure functions.
 *
 * A readout line ($say) is [bold lead, rest of the sentence], one per readable step; see Format::line().
 */
final class Charts
{
    /**
     * Axis ticks as [[value, label]]: ticks([0, 50, 100], 'h') → [[0, '0h'], [50, '50h'], [100, '100h']].
     *
     * @param  list<int|float>  $values
     * @return list<array{0: int|float, 1: string}>
     */
    public static function ticks(array $values, string $suffix = ''): array
    {
        return array_map(fn (int|float $value): array => [$value, Format::number($value, self::decimals($value)).$suffix], $values);
    }

    /**
     * A line chart. $axes: x0, x1, y1, yt and xt as ticks, optional w and h, optional lead (the series the readout follows);
     * $series: [['cls' => 'a', 'pts' => [[x, y]], 'band' => [[x, lo, hi]], 'mark' => index]].
     *
     * @param  array<string, mixed>  $axes
     * @param  list<array<string, mixed>>  $series
     * @param  list<array{0: string, 1: string}>|null  $say
     * @return array<string, mixed>
     */
    public static function line(array $axes, array $series, ?array $say = null, ?int $rest = null, ?string $out = null): array
    {
        $series = array_map(fn (array $s): array => [...$s, 'pts' => self::points($s['pts']), ...(isset($s['band']) ? ['band' => self::points($s['band'])] : [])], $series);

        return self::reading(['kind' => 'line', ...$axes, 'series' => $series], $say, $rest, $out);
    }

    /**
     * Step lines: a value that holds until it changes. $series: [['cls' => 'p1', 'pts' => [[x, y]]]], each line starting at its
     * first point and holding its last value to $axes' x1; $samples: [[x, [y of each series, or null before its first point]]],
     * the positions the reader steps through, one $say line each.
     *
     * @param  array<string, mixed>  $axes
     * @param  list<array<string, mixed>>  $series
     * @param  list<array{0: float, 1: list<int|null>}>  $samples
     * @param  list<array{0: string, 1: string}>  $say
     * @return array<string, mixed>
     */
    public static function steps(array $axes, array $series, array $samples, array $say, int $rest, string $out): array
    {
        $series = array_map(fn (array $s): array => [...$s, 'pts' => self::points($s['pts'])], $series);

        return self::reading(['kind' => 'steps', ...$axes, 'series' => $series, 'samples' => $samples], $say, $rest, $out);
    }

    /**
     * A bar chart. $data: [[x, value, lo, hi, tick]]; lo and hi draw a whisker, tick a mark across the bar at that value.
     *
     * @param  array<string, mixed>  $axes
     * @param  list<list<int|float|null>>  $data
     * @param  list<array{0: string, 1: string}>|null  $say
     * @return array<string, mixed>
     */
    public static function bars(array $axes, array $data, string $cls = '', ?array $say = null, ?int $rest = null, ?string $out = null): array
    {
        return self::reading(['kind' => 'bars', ...$axes, 'cls' => $cls, 'data' => self::points($data)], $say, $rest, $out);
    }

    /**
     * Stacked bars. $data: [[x, [part, part, ...]]], the parts from the bottom up; $classes: the class of each part.
     *
     * @param  array<string, mixed>  $axes
     * @param  list<array{0: int, 1: list<float>}>  $data
     * @param  list<string>  $classes
     * @param  list<array{0: string, 1: string}>|null  $say
     * @return array<string, mixed>
     */
    public static function stack(array $axes, array $data, array $classes, ?array $say = null, ?int $rest = null, ?string $out = null): array
    {
        return self::reading(['kind' => 'stack', ...$axes, 'cls' => $classes, 'data' => $data], $say, $rest, $out);
    }

    /**
     * @param  array{w: int, x0: int, x1: int, rows: int, peak: int, xt: list<array{0: int|float, 1: string}>}  $axes
     * @param  list<array{0: int, 1: int, 2: int}>  $data
     * @param  list<array{0: string, 1: string}>|null  $say
     * @return array<string, mixed>
     */
    public static function dots(array $axes, array $data, ?array $say = null, ?int $rest = null, ?string $out = null): array
    {
        return self::reading(['kind' => 'dots', ...$axes, 'data' => $data], $say, $rest, $out);
    }

    /**
     * Stacked shares, one row per step: [kill, dungeon, quest, explore, other], each a share of 1.
     *
     * @param  list<list<int|float>>  $rows
     * @param  list<array{0: string, 1: string}>|null  $say
     * @return array<string, mixed>
     */
    public static function shares(array $rows, ?array $say = null, ?int $rest = null): array
    {
        return self::reading(['kind' => 'shares', 'rows' => self::points($rows)], $say, $rest, null);
    }

    /**
     * Hours by level. $data: [[level, hours or null, [kill, dungeon, quest, explore, other] shares or null, ruleset median hours or null, deaths]],
     * one row per level from the first to the last; the bar of a level is split by its shares.
     *
     * @param  array<string, mixed>  $axes
     * @param  list<array{0: int, 1: float|null, 2: list<float>|null, 3: float|null, 4: int}>  $data
     * @param  list<array{0: string, 1: string}>|null  $say
     * @return array<string, mixed>
     */
    public static function levels(array $axes, array $data, ?array $say = null, ?int $rest = null, ?string $out = null): array
    {
        return self::reading(['kind' => 'levels', ...$axes, 'data' => array_map(fn (array $row): array => [$row[0], $row[1] === null ? null : round($row[1], 3), $row[2], $row[3] === null ? null : round($row[3], 3), $row[4]], $data)], $say, $rest, $out);
    }

    /**
     * Play time by weekday and hour: $levels is 7 rows of 24 levels from 0 to 4, Monday first; $say has one line per cell, a day at a time.
     *
     * $rest is the cell the keyboard starts on and $summary the line the readout shows while no cell is read.
     *
     * @param  list<list<int>>  $levels
     * @param  list<array{0: string, 1: string}>|null  $say
     * @param  array{0: string, 1: string}|null  $summary
     * @return array<string, mixed>
     */
    public static function hours(array $levels, ?array $say = null, ?string $out = null, ?int $rest = null, ?array $summary = null): array
    {
        return self::reading(['kind' => 'hours', 'days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], 'levels' => $levels], $say, $rest, $out, $summary);
    }

    /**
     * The hour grid drawn from the 168 UTC cells of the week, Monday 00:00 first; resources/js/local-time.js
     * reads them in the viewer's time zone. $busiest is the cell the page names as the busiest hour, so a tie picks
     * the same hour in the grid.
     *
     * @param  array<int, float>  $cells
     * @return array<string, mixed>
     */
    public static function weekCells(array $cells, ?int $busiest, ?string $out = null): array
    {
        return self::reading(['kind' => 'hours', 'cells' => $cells, 'busiest' => $busiest], null, null, $out);
    }

    /**
     * Play time by day: $days is [[level 0 to 4, hidden]] a week at a time from Monday; $months has one label per week.
     *
     * @param  list<array{0: int, 1: int}>  $days
     *                                             $rest is the cell the keyboard starts on and $summary the line the readout shows while no cell is read.
     * @param  list<string>  $months
     * @param  list<array{0: string, 1: string}>|null  $say
     * @param  array{0: string, 1: string}|null  $summary
     * @return array<string, mixed>
     */
    public static function days(array $days, array $months, ?array $say = null, ?string $out = null, ?int $rest = null, ?array $summary = null): array
    {
        return self::reading(['kind' => 'days', 'days' => $days, 'months' => $months], $say, $rest, $out, $summary);
    }

    /**
     * The survey map. $cells: [[cell x, cell y, level 1 to 4]] on a grid of 83 map units; $deaths: [[x, y]] in map units.
     * $box is the part of the sheet in view: x0, y0, x1, y1.
     *
     * @param  list<array{0: int, 1: int, 2: int}>  $cells
     * @param  list<array{0: int, 1: int}>  $deaths
     * @param  array{x0: int, y0: int, x1: int, y1: int}|null  $box
     * @return array<string, mixed>
     */
    public static function map(array $cells = [], array $deaths = [], ?array $box = null): array
    {
        return array_filter(['kind' => 'map', 'box' => $box, 'cells' => $cells, 'deaths' => $deaths], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  list<array{0: string, 1: string}>|null  $say
     * @param  array{0: string, 1: string}|null  $summary
     * @return array<string, mixed>
     */
    private static function reading(array $spec, ?array $say, ?int $rest, ?string $out, ?array $summary = null): array
    {
        return array_filter([...$spec, 'say' => $say, 'rest' => $rest, 'out' => $out, 'summary' => $summary], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Keeps three decimals, so a page does not ship float noise.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<int, mixed>>
     */
    private static function points(array $rows): array
    {
        return array_map(fn (array $row): array => array_map(fn (mixed $v): mixed => is_float($v) ? round($v, 3) : $v, $row), $rows);
    }

    private static function decimals(int|float $value): int
    {
        return floor($value) === (float) $value ? 0 : 1;
    }
}
