<?php

namespace App\View\Pages;

/** Axis ranges that end on a round number, so a chart's grid lines read as 0, 50, 100. */
final class Axis
{
    /**
     * The top of an axis for values up to $max and its ticks, from 0.
     *
     * @return array{max: int|float, ticks: list<int|float>}
     */
    public static function nice(float $max, int $steps = 4): array
    {
        if ($max <= 0) {
            return ['max' => 1, 'ticks' => [0, 1]];
        }
        $raw = $max / $steps;
        $magnitude = 10 ** floor(log10($raw));
        $unit = $raw / $magnitude;
        $step = match (true) {
            $unit <= 1 => 1,
            $unit <= 2 => 2,
            $unit <= 2.5 => 2.5,
            $unit <= 5 => 5,
            default => 10,
        } * $magnitude;
        $top = ceil($max / $step - 1e-9) * $step;
        $ticks = [];
        for ($value = 0.0; $value <= $top + 1e-9; $value += $step) {
            $ticks[] = round($value, 6);
        }

        return ['max' => $top, 'ticks' => $ticks];
    }

    /**
     * Evenly spread x ticks over a range of whole numbers: the first, the last and about $count between.
     *
     * @return list<int>
     */
    public static function spread(int $from, int $to, int $count = 5): array
    {
        if ($to <= $from) {
            return [$from];
        }
        $ticks = [$from];
        $step = max(1, (int) round(($to - $from) / $count));
        for ($value = $from + $step; $value < $to - intdiv($step, 2); $value += $step) {
            $ticks[] = $value;
        }
        $ticks[] = $to;

        return $ticks;
    }
}
