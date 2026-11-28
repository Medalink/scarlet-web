<?php

namespace App\View\Pages\Admin;

use App\View\Charts;
use App\View\Dates;
use App\View\Format;
use App\View\Pages\Axis;

/**
 * What the four admin pages share (docs/comps/admin*.html): the page nav, the UTC days a chart spans, which end at
 * the last full day so no chart ends on half a day, and the line, bar and stacked charts drawn over those days.
 */
final class AdminCharts
{
    /** The size of a chart in a third of the sheet. */
    public const array THIRD = ['w' => 380, 'h' => 250];

    public const array WIDE = ['w' => 760, 'h' => 250];

    private const array PAGES = [['Overview', '/admin'], ['Characters', '/admin/characters'], ['API', '/admin/api'], ['Clients', '/admin/clients'], ['Limits', '/admin/limits'], ['Database', '/admin/database'], ['Ingest', '/admin/ingest']];

    /** @return list<array{label: string, href: string, on: bool}> the segmented nav between the admin pages */
    public static function nav(string $current): array
    {
        return array_map(fn (array $page): array => ['label' => $page[0], 'href' => $page[1], 'on' => $page[1] === $current], self::PAGES);
    }

    /** @return list<string> the last $count full UTC days, oldest first, as Y-m-d */
    public static function days(int $count): array
    {
        $last = now('UTC')->startOfDay()->subDay();

        return array_map(fn (int $i): string => $last->copy()->subDays($count - 1 - $i)->toDateString(), range(0, $count - 1));
    }

    public static function lastDay(): string
    {
        return Dates::text(now('UTC')->startOfDay()->subDay()->toDateString());
    }

    /**
     * @param  list<string>  $days
     * @param  array<string, int|float>  $values
     * @return list<int|float> the value of each day, 0 for a day with none
     */
    public static function over(array $days, array $values): array
    {
        return array_map(fn (string $day): int|float => $values[$day] ?? 0, $days);
    }

    /**
     * Lines over the days, the last series marked at its peak. $series: [['cls' => 'a', 'values' => [n per day]]].
     *
     * @param  list<string>  $days
     * @param  list<array{cls: string, values: list<int|float>}>  $series
     * @param  array{w: int, h: int}  $size
     * @param  list<array{0: string, 1: string}>|null  $say
     * @param  callable(int|float): string|null  $label  the y tick label
     * @return array<string, mixed>
     */
    public static function lines(array $days, array $series, array $size, ?array $say = null, ?callable $label = null): array
    {
        $axis = self::axis(array_merge(...array_map(fn (array $s): array => $s['values'], $series)));
        $tail = count($days) > 60 ? 3 : 1;
        $drawn = [];
        foreach ($series as $at => $s) {
            $line = ['cls' => $s['cls'], 'pts' => array_map(fn (int $i, int|float $v): array => [$i, $v], array_keys($s['values']), $s['values'])];
            if ($at === array_key_last($series)) {
                $line['mark'] = (int) array_search(max([0, ...$s['values']]), $s['values'], true);
            }
            $drawn[] = $line;
        }

        return Charts::line(
            [...$size, 'x0' => 0, 'x1' => count($days) - 1 + $tail, 'y1' => $axis['max'], 'yt' => self::yTicks($axis['ticks'], $label), 'xt' => self::xTicks($days, $size)],
            $drawn,
            $say,
            $say === null ? null : count($say) - 1,
        );
    }

    /**
     * @param  list<string>  $days
     * @param  list<int|float>  $values
     * @param  array{w: int, h: int}  $size
     * @return array<string, mixed>
     */
    public static function bars(array $days, array $values, array $size, string $cls = ''): array
    {
        $axis = self::axis($values);

        return Charts::bars(
            [...$size, 'x0' => -1, 'x1' => count($days), 'y1' => $axis['max'], 'yt' => self::yTicks($axis['ticks'], null), 'xt' => self::xTicks($days, $size)],
            array_map(fn (int $i, int|float $v): array => [$i, $v], array_keys($values), $values),
            $cls,
        );
    }

    /**
     * Bars split into parts, bottom up. $parts: one list of day values per part.
     *
     * @param  list<string>  $days
     * @param  list<list<int|float>>  $parts
     * @param  list<string>  $classes
     * @param  list<array{0: string, 1: string}>  $say
     * @return array<string, mixed>
     */
    public static function stack(array $days, array $parts, array $classes, array $say): array
    {
        $totals = array_map(fn (int $i): int|float => array_sum(array_column($parts, $i)), array_keys($days));
        $axis = self::axis($totals);
        $data = array_map(fn (int $i): array => [$i, array_map(fn (array $part): float => (float) $part[$i], $parts)], array_keys($days));

        return Charts::stack(
            [...self::WIDE, 'x0' => -1, 'x1' => count($days), 'y1' => $axis['max'], 'yt' => self::yTicks($axis['ticks'], null), 'xt' => self::xTicks($days, self::WIDE)],
            $data,
            $classes,
            $say,
            count($say) - 1,
        );
    }

    /**
     * @param  list<string>  $days
     * @param  list<int|float>  $values
     */
    public static function most(array $days, array $values, string $what): string
    {
        $top = max([0, ...$values]);
        if ($top <= 0) {
            return 'Nothing yet in these days.';
        }
        $at = (int) array_search($top, $values, true);

        return 'Most in one day: '.Format::number($top).' '.$what.' on '.Dates::text($days[$at]).'.';
    }

    /**
     * Parts of one whole for a stack bar and its figures. $parts: [['class', 'label', 'count']].
     *
     * @param  list<array{0: string, 1: string, 2: int}>  $parts
     * @return list<array{class: string, label: string, count: int, value: string, share: string}>
     */
    public static function split(array $parts): array
    {
        $total = max(1, array_sum(array_column($parts, 2)));

        return array_map(fn (array $part): array => [
            'class' => $part[0], 'label' => $part[1], 'count' => $part[2],
            'value' => Format::number($part[2]), 'share' => Format::percent($part[2] / $total, 1),
        ], $parts);
    }

    /** Thousands as "15k" past 10,000, so a third-width axis stays narrow, and a fraction with at most two decimals. */
    public static function short(int|float $value): string
    {
        if ($value >= 10000) {
            return Format::number($value / 1000, fmod((float) $value, 1000) === 0.0 ? 0 : 1).'k';
        }

        return rtrim(rtrim(Format::number($value, 2), '0'), '.');
    }

    /**
     * The y axis of a chart: whole counts get at least 0 to 4, so a quiet chart has no repeated tick labels.
     *
     * @param  list<int|float>  $values
     * @return array{max: int|float, ticks: list<int|float>}
     */
    private static function axis(array $values): array
    {
        $top = max([0, ...$values]);
        $whole = array_filter($values, fn (int|float $value): bool => ! is_int($value)) === [];

        return Axis::nice((float) ($whole ? max(4, $top) : ($top > 0 ? $top : 1)));
    }

    /**
     * @param  list<string>  $days
     * @param  array{w: int, h: int}  $size
     * @return list<array{0: int, 1: string}>
     */
    private static function xTicks(array $days, array $size): array
    {
        $at = Axis::spread(0, count($days) - 1, $size['w'] < 600 ? 2 : 4);

        return array_map(fn (int $i): array => [$i, Dates::text($days[$i])], $at);
    }

    /**
     * @param  list<int|float>  $ticks
     * @param  callable(int|float): string|null  $label
     * @return list<array{0: int|float, 1: string}>
     */
    private static function yTicks(array $ticks, ?callable $label): array
    {
        $label ??= self::short(...);

        return array_map(fn (int|float $tick): array => [$tick, $label($tick)], $ticks);
    }
}
