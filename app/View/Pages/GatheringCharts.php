<?php

namespace App\View\Pages;

use App\Support\GatherKind;
use App\View\Charts;
use App\View\Format;

/** The charts of a gathering page, as the specs resources/js/charts.js draws. */
final class GatheringCharts
{
    /** The last step of the wait aggregate: 30 seconds and over. */
    private const int LAST_STEP = 15;

    /** The seconds one step of the wait aggregate covers. */
    private const int STEP_SECONDS = 2;

    /** The ranks one band of the per-point aggregate covers. */
    private const int BAND = 25;

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

    /**
     * Attempts for each rise in rank, by the rank before the attempt. A band no one rose in is left out.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $w
     * @return array{spec: array<string, mixed>, note: string}|null
     */
    public static function perPoint(array $rows, array $w): ?array
    {
        $rows = array_values(array_filter($rows, fn (array $row): bool => (int) $row['skill_ups'] > 0));
        if (count($rows) < 2) {
            return null;
        }
        $data = $say = $ticks = [];
        foreach ($rows as $i => $row) {
            $per = (int) $row['looted'] / (int) $row['skill_ups'];
            $data[] = [$i, $per];
            $ticks[] = [$i, (string) $row['band']];
            $say[] = Format::line('Skill '.$row['band'].' to '.($row['band'] + self::BAND - 1), ': '.Format::number($per, 1).' '.$w['results'].' for a skill point, from '.Format::count((int) $row['skill_ups'], 'skill point'));
        }
        $axis = Axis::nice(max(array_column($data, 1)), 3);
        $last = count($rows) - 1;
        $slowest = (int) array_search(max(array_column($data, 1)), array_column($data, 1), true);

        return [
            'spec' => Charts::bars(
                ['w' => 400, 'h' => 250, 'x0' => -0.5, 'x1' => $last + 0.5, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks']), 'xt' => count($ticks) > 8 ? array_values(array_filter($ticks, fn (array $tick): bool => $tick[0] % 2 === 0)) : $ticks],
                $data, 't', $say, $slowest,
            ),
            'note' => 'Slowest: skill '.$rows[$slowest]['band'].' to '.($rows[$slowest]['band'] + self::BAND - 1).', at '.Format::number($data[$slowest][1], 1).' '.$w['results'].' for a skill point.',
        ];
    }

    /**
     * The median skill of the characters at each character level.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    public static function byLevel(array $rows): ?array
    {
        $rows = array_values(array_filter($rows, fn (array $row): bool => $row['median_skill'] !== null));
        if (count($rows) < 2) {
            return null;
        }
        $pts = $say = [];
        foreach ($rows as $row) {
            $pts[] = [(int) $row['level'], (float) $row['median_skill']];
            $say[] = Format::line('Level '.$row['level'], ': median skill '.Format::number((float) $row['median_skill']).', from '.Format::count((int) $row['characters'], 'character'));
        }
        $first = (int) $rows[0]['level'];
        $top = max($first + 1, (int) $rows[count($rows) - 1]['level']);

        return Charts::line(
            ['w' => 380, 'h' => 250, 'x0' => $first, 'x1' => $top, 'y1' => GatherKind::SKILL_CAP, 'yt' => Charts::ticks([0, 75, 150, 225, GatherKind::SKILL_CAP]), 'xt' => Charts::ticks(Axis::spread($first, $top, 4))],
            [['cls' => 'a', 'pts' => $pts]], $say, count($pts) - 1,
        );
    }
}
