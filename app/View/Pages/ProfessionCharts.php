<?php

namespace App\View\Pages;

use App\Support\Profession;
use App\View\Charts;
use App\View\Format;

/** The charts a gathering page and a crafting page share, as the specs resources/js/charts.js draws. */
final class ProfessionCharts
{
    /** The ranks one band of the per-point aggregate covers. */
    private const int BAND = 25;

    /**
     * Attempts or crafts for each rise in rank, by the rank before it, counted under `$count`. A band no one rose in is left out.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{spec: array<string, mixed>, note: string}|null
     */
    public static function perPoint(array $rows, string $count, string $results): ?array
    {
        $rows = array_values(array_filter($rows, fn (array $row): bool => (int) $row['skill_ups'] > 0));
        if (count($rows) < 2) {
            return null;
        }
        $data = $say = $ticks = [];
        foreach ($rows as $i => $row) {
            $per = (int) $row[$count] / (int) $row['skill_ups'];
            $data[] = [$i, $per];
            $ticks[] = [$i, (string) $row['band']];
            $say[] = Format::line('Skill '.$row['band'].' to '.($row['band'] + self::BAND - 1), ': '.Format::number($per, 1).' '.$results.' for a skill point, from '.Format::count((int) $row['skill_ups'], 'skill point'));
        }
        $axis = Axis::nice(max(array_column($data, 1)), 3);
        $last = count($rows) - 1;
        $slowest = (int) array_search(max(array_column($data, 1)), array_column($data, 1), true);

        return [
            'spec' => Charts::bars(
                ['w' => 400, 'h' => 250, 'x0' => -0.5, 'x1' => $last + 0.5, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks']), 'xt' => count($ticks) > 8 ? array_values(array_filter($ticks, fn (array $tick): bool => $tick[0] % 2 === 0)) : $ticks],
                $data, 't', $say, $slowest,
            ),
            'note' => 'Slowest: skill '.$rows[$slowest]['band'].' to '.($rows[$slowest]['band'] + self::BAND - 1).', at '.Format::number($data[$slowest][1], 1).' '.$results.' for a skill point.',
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
            ['w' => 380, 'h' => 250, 'x0' => $first, 'x1' => $top, 'y1' => Profession::SKILL_CAP, 'yt' => Charts::ticks([0, 75, 150, 225, Profession::SKILL_CAP]), 'xt' => Charts::ticks(Axis::spread($first, $top, 4))],
            [['cls' => 'a', 'pts' => $pts]], $say, count($pts) - 1,
        );
    }
}
