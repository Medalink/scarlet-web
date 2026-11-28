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
     * X ticks over a range of whole numbers: the first, the multiples of a round step ("1, 10, 20, 30" on a level axis)
     * and the last, about $count between.
     *
     * @return list<int>
     */
    public static function spread(int $from, int $to, int $count = 5): array
    {
        if ($to <= $from) {
            return [$from];
        }
        $step = self::step(($to - $from) / max(1, $count));
        $ticks = [$from];
        for ($value = (intdiv($from, $step) + 1) * $step; $value < $to; $value += $step) {
            if ($value - $ticks[array_key_last($ticks)] >= $step / 2) {
                $ticks[] = $value;
            }
        }
        if ($to - $ticks[array_key_last($ticks)] < $step / 2 && count($ticks) > 1) {
            array_pop($ticks);
        }
        $ticks[] = $to;

        return $ticks;
    }

    /** The round step (1, 2, 5, 10, 20, 50 ...) nearest to a raw one. */
    private static function step(float $raw): int
    {
        $magnitude = 10 ** floor(log10(max(1.0, $raw)));
        $best = 1;
        $gap = INF;
        foreach ([1, 2, 5, 10, 20] as $unit) {
            $candidate = (int) ($unit * $magnitude);
            $distance = abs(log($candidate / max(1.0, $raw)));
            if ($distance < $gap) {
                $gap = $distance;
                $best = max(1, $candidate);
            }
        }

        return $best;
    }
}
