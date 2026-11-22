<?php

namespace App\View\ClassPage;

use App\Support\ClassPage\Mirror;

/**
 * The geometry of a mirror chart in an SVG 760 by 290: bars up and down from one axis at each level, the axis
 * ending at a round number above the largest bar. Returns null for a chart with no points or nothing to draw.
 */
final class MirrorLayout
{
    private const int WIDTH = 760;

    private const int HEIGHT = 290;

    private const int LEFT = 44;

    private const int RIGHT = 6;

    private const int TOP = 10;

    private const int BOTTOM = 24;

    private const array NICE = [1, 2, 3, 4, 5, 6, 8, 10];

    /**
     * @return array{width: int, height: int, grid: list<array{y: string, ly: string, label: string, axis: bool}>, ticks: list<array{x: string, label: int}>, bars: list<array{x: string, w: string, upY: string, upH: string, downY: string, downH: string, on: bool}>, peak: array{x: string, y: string, label: string}}|null
     */
    public static function of(Mirror $mirror): ?array
    {
        $points = $mirror->points;
        $top = 0.0;
        foreach ($points as $point) {
            $top = max($top, $point->up, $point->down);
        }
        if ($points === [] || $top <= 0) {
            return null;
        }
        $max = self::round($top);
        $half = (self::HEIGHT - self::TOP - self::BOTTOM) / 2;
        $mid = self::TOP + $half;
        $first = $points[0]->level;
        $last = $points[array_key_last($points)]->level;
        $span = $last - $first + 1;
        $plotWidth = self::WIDTH - self::LEFT - self::RIGHT;
        $x = fn (int $level): float => self::LEFT + (($level - ($first - 0.5)) / $span) * $plotWidth;
        $barWidth = $plotWidth / $span * 0.72;

        $grid = [];
        foreach ([$max, $max / 2, 0, -$max / 2, -$max] as $tick) {
            $y = $mid - $tick / $max * $half;
            $grid[] = ['y' => self::f($y), 'ly' => self::f($y + 3.5), 'label' => $tick === 0 ? '0' : $mirror->unit->say(abs($tick)), 'axis' => $tick === 0];
        }
        $ticks = [];
        foreach (self::levels($first, $last) as $level) {
            $ticks[] = ['x' => self::f($x($level)), 'label' => $level];
        }
        $peak = $points[0];
        foreach ($points as $point) {
            $peak = $point->up > $peak->up ? $point : $peak;
        }
        $bars = [];
        foreach ($points as $point) {
            $up = $point->up / $max * $half;
            $down = $point->down / $max * $half;
            $bars[] = [
                'x' => self::f($x($point->level) - $barWidth / 2), 'w' => self::f($barWidth),
                'upY' => self::f($mid - $up), 'upH' => self::f($up), 'downY' => self::f($mid + 1), 'downH' => self::f($down),
                'on' => $point === $peak,
            ];
        }

        return [
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'grid' => $grid,
            'ticks' => $ticks,
            'bars' => $bars,
            'peak' => ['x' => self::f($x($peak->level)), 'y' => self::f($mid - $peak->up / $max * $half - 6), 'label' => 'level '.$peak->level],
        ];
    }

    /** The smallest 1, 2, 3, 4, 5, 6 or 8 times a power of ten that is at least the value. */
    public static function round(float|int $value): float
    {
        $power = 10 ** floor(log10($value));
        foreach (self::NICE as $step) {
            if ($step * $power >= $value) {
                return $step * $power;
            }
        }

        return 10 * $power;
    }

    /** @return list<int> the tens inside the range, or its two ends when it holds none */
    private static function levels(int $first, int $last): array
    {
        $tens = [];
        for ($level = (int) (ceil($first / 10) * 10); $level <= $last; $level += 10) {
            $tens[] = $level;
        }

        return $tens === [] ? array_values(array_unique([$first, $last])) : $tens;
    }

    private static function f(float|int $value): string
    {
        return number_format($value, 1, '.', '');
    }
}
