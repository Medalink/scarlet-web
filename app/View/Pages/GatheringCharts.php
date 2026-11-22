<?php

namespace App\View\Pages;

use App\View\Charts;
use App\View\Format;

/** The charts of a gathering page, as the specs resources/js/charts.js draws. */
final class GatheringCharts
{
    /** The last step of the wait aggregate: 30 seconds and over. */
    private const int LAST_STEP = 15;

    /** The seconds one step of the wait aggregate covers. */
    private const int STEP_SECONDS = 2;

    /**
     * Looted attempts by the seconds they took. Left out when every attempt is in one step, which is a gathering that takes
     * a fixed time.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $w
     * @return array{spec: array<string, mixed>, note: string|null}|null
     */
    public static function waits(array $rows, array $w): ?array
    {
        /** @var array<int, int> $by */
        $by = array_column($rows, 'gathers', 'step');
        $total = (int) array_sum($by);
        if (count($by) < 2 || $total === 0) {
            return null;
        }
        $data = $say = [];
        foreach (range(0, self::LAST_STEP) as $step) {
            $count = (int) ($by[$step] ?? 0);
            $data[] = [$step, $count / $total * 100];
            $from = $step * self::STEP_SECONDS;
            $range = $step === self::LAST_STEP ? $from.' seconds and over' : $from.' to '.($from + self::STEP_SECONDS).' seconds';
            $say[] = Format::line(ucfirst($range), ': '.Format::count($count, $w['wait']).', '.Format::percent($count / $total, 1));
        }
        $axis = Axis::nice(max(array_column($data, 1)), 3);
        $peak = (int) array_search(max(array_column($data, 1)), array_column($data, 1), true);

        return [
            'spec' => Charts::bars(
                ['w' => 400, 'h' => 250, 'x0' => -0.5, 'x1' => self::LAST_STEP + 0.5, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks'], '%'), 'xt' => [[0, '0s'], [5, '10s'], [10, '20s'], [15, '30s+']]],
                $data, '', $say, $peak,
            ),
            'note' => self::middleHalf($by, $total, $w['wait']),
        ];
    }

    /**
     * @param  array<int, int>  $by  looted attempts by step
     */
    private static function middleHalf(array $by, int $total, string $wait): ?string
    {
        ksort($by);
        $running = 0;
        $low = $high = null;
        foreach ($by as $step => $count) {
            $running += $count;
            $low ??= $running >= $total * 0.25 ? $step : null;
            $high ??= $running >= $total * 0.75 ? $step : null;
        }
        if ($low === null || $high === null || $high === self::LAST_STEP) {
            return null;
        }

        return 'Half of all '.$wait.'s take '.($low * self::STEP_SECONDS).' to '.(($high + 1) * self::STEP_SECONDS).' seconds.';
    }
}
