<?php

namespace App\View\Design;

use App\View\Charts;
use App\View\Dates;
use App\View\Format;

/** The data of docs/comps/stats.html. */
final class StatsPage
{
    /**
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        $r = new Rng(11);

        return [
            'figures' => [
                ['label' => 'Characters', 'value' => Format::number(1284)],
                ['label' => 'Hours played', 'value' => Format::number(41907)],
                ['label' => 'Median to level 60', 'value' => Format::duration(6 * 86400 + 4 * 3600), 'unit' => 'played'],
                ['label' => 'Deaths', 'value' => Format::number(58310)],
                ['label' => 'Quests turned in', 'value' => Format::number(402118)],
                ['label' => 'Dungeon runs', 'value' => Format::number(9446)],
            ],
            'curve' => self::curve(),
            'reach' => self::reach($r),
            'xp' => self::xp($r),
            'ltime' => self::levelTime($r),
            'dph' => self::deathsPerHour($r),
            'killers' => self::rank([['Marsh Stalker', '2,104 deaths', 2104, 'creature'], ['Hollow Warden', '1,655', 1655, 'creature'], ['Falling', '1,402', 1402, null], ['Saltmarsh Raider', '1,118', 1118, 'creature'], ['Another player', '940', 940, null], ['Drowning', '611', 611, null]]),
            'deathZones' => self::rank([['Saltmarsh Reach', '6,480 deaths', 6480, 'zone'], ['Hollowmere', '5,022', 5022, 'zone'], ['The Cinder Flats', '3,870', 3870, 'zone'], ['Greywater Deep', '2,911', 2911, 'zone'], ['Thornwall', '2,240', 2240, 'zone']]),
            'dungeons' => [
                ['Greywater Deep', 2418, 42 * 60, 1.8, 22], ['The Ember Vault', 1960, 58 * 60, 2.6, 31], ['Thornwall Keep', 1512, 74 * 60, 3.1, 38],
                ['Sunken Archive', 1203, 51 * 60, 2.2, 44], ['Hall of Ash', 877, 92 * 60, 4.4, 55],
            ],
            'professions' => self::rank([['Fishing', '71% of characters · median 118', 71, null], ['Cooking', '64% · 142', 64, null], ['Mining', '38% · 165', 38, null], ['Herbalism', '35% · 171', 35, null], ['Skinning', '33% · 190', 33, null]], false),
            'gold' => self::gold(),
            'hours' => self::hours($r),
            'map' => Heat::map(5, [[3600, 4800, 900, 520], [5600, 4200, 700, 380], [7200, 3300, 650, 300], [4700, 2600, 500, 160], [6400, 5300, 520, 150]], [[3900, 4500], [5200, 4400], [5750, 3900], [7100, 3000], [6900, 3600], [4500, 2700], [3300, 5100]]),
        ];
    }

    /**
     * Rank rows from [label, value, size, page]: a row links to its gallery page when it has one.
     *
     * @param  list<list<mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function rank(array $rows, bool $scaled = true): array
    {
        $shares = $scaled ? Gallery::shares(array_column($rows, 2)) : array_map(floatval(...), array_column($rows, 2));

        return array_map(fn (array $row, float $share): array => [
            'label' => $row[0], 'value' => $row[1], 'share' => $share, 'href' => $row[3] === null ? null : Gallery::url($row[3]),
        ], $rows, $shares);
    }

    /**
     * @return array<string, mixed>
     */
    private static function curve(): array
    {
        $median = $band = $fast = [];
        $t = 0.0;
        $say = [];
        for ($l = 1; $l <= 60; $l++) {
            $t += 0.12 + (($l / 60) ** 2.1) * 5.2;
            $median[] = [$l, $t];
            $band[] = [$l, $t * 0.78, $t * 1.29];
            $fast[] = [$l, $t * 0.6];
            $say[] = Format::line('Level '.$l, ' takes a median of '.Format::hours($t * 3600).' played. The middle half is '.Format::hours($t * 0.78 * 3600, false).' to '.Format::hours($t * 1.29 * 3600, false).'.');
        }

        return [
            'spec' => Charts::line(
                ['x0' => 1, 'x1' => 60, 'y1' => 200, 'yt' => Charts::ticks([0, 50, 100, 150, 200], 'h'), 'xt' => Charts::ticks([1, 10, 20, 30, 40, 50, 60])],
                [['cls' => 'a', 'pts' => $median, 'band' => $band, 'mark' => 39], ['cls' => 'b thin', 'pts' => $fast]],
                $say, 39, 'curve-out',
            ),
            'line' => $say[39],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function reach(Rng $r): array
    {
        $data = $say = [];
        for ($i = 0; $i < 60; $i++) {
            $v = 70 * exp(-$i / 21) * (0.75 + $r->next() * 0.5) + ($i === 59 ? 34 : 0);
            $data[] = [$i + 1, $v];
            $say[] = Format::line('Level '.($i + 1), ': '.Format::number($v).' characters');
        }

        return Charts::bars(['w' => 380, 'h' => 250, 'x0' => 0, 'x1' => 61, 'y1' => 80, 'yt' => Charts::ticks([0, 40, 80]), 'xt' => Charts::ticks([1, 20, 40, 60])], $data, 't', $say);
    }

    /**
     * @return array<string, mixed>
     */
    private static function xp(Rng $r): array
    {
        $rows = $say = [];
        for ($i = 0; $i < 59; $i++) {
            $q = 0.5 - $i * 0.002 + $r->next() * 0.06;
            $d = $i < 14 ? 0 : 0.1 + $r->next() * 0.14;
            $e = 0.07 - $i * 0.001;
            $o = 0.015;
            $kill = 1 - $q - $d - $e - $o;
            $rows[] = [$kill, $d, $q, $e, $o];
            $parts = ['kills' => $kill, 'dungeon kills' => $d, 'quests' => $q, 'exploring' => $e, 'other' => $o];
            arsort($parts);
            $text = [];
            foreach ($parts as $name => $share) {
                if ($share >= 0.005) {
                    $text[] = $name.' '.Format::percent($share);
                }
            }
            $say[] = Format::line('Level '.($i + 1), ': '.implode(', ', $text).'.');
        }

        return ['spec' => Charts::shares($rows, $say, 33), 'line' => $say[33]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function levelTime(Rng $r): array
    {
        $data = $say = [];
        for ($i = 0; $i < 59; $i++) {
            $v = 0.15 + ((($i + 1) / 59) ** 1.8) * 4.6 * (0.9 + $r->next() * 0.2);
            $data[] = [$i + 1, $v];
            $say[] = Format::line('Level '.($i + 1), ': '.Format::duration($v * 3600).' median');
        }

        return Charts::bars(['w' => 380, 'h' => 250, 'x0' => 0, 'x1' => 60, 'y1' => 6, 'yt' => Charts::ticks([0, 2, 4, 6], 'h'), 'xt' => Charts::ticks([1, 20, 40, 59])], $data, '', $say);
    }

    /**
     * @return array<string, mixed>
     */
    private static function deathsPerHour(Rng $r): array
    {
        $pts = $say = [];
        for ($i = 0; $i < 60; $i++) {
            $v = 0.25 + 0.6 * exp(-((($i - 25) / 9) ** 2)) + $r->next() * 0.08;
            $pts[] = [$i + 1, $v];
            $say[] = Format::line('Level '.($i + 1), ': '.Format::number($v, 2).' deaths an hour');
        }

        return Charts::line(['w' => 380, 'h' => 250, 'x0' => 1, 'x1' => 60, 'y1' => 1.2, 'yt' => Charts::ticks([0, 0.4, 0.8, 1.2]), 'xt' => Charts::ticks([1, 20, 40, 60])], [['cls' => 'a', 'pts' => $pts]], $say);
    }

    /**
     * @return array<string, mixed>
     */
    private static function gold(): array
    {
        $pts = $band = [];
        $m = 0.0;
        for ($l = 1; $l <= 60; $l++) {
            $m += (($l / 60) ** 1.7) * 6;
            $pts[] = [$l, $m];
            $band[] = [$l, $m * 0.55, $m * 1.7];
        }

        return Charts::line(['w' => 480, 'h' => 250, 'x0' => 1, 'x1' => 60, 'y1' => 260, 'yt' => Charts::ticks([0, 100, 200], 'g'), 'xt' => Charts::ticks([1, 20, 40, 60])], [['cls' => 'g', 'pts' => $pts, 'band' => $band]]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function hours(Rng $r): array
    {
        $levels = $say = [];
        $names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $words = ['Quiet', 'Light', 'Moderate', 'Busy', 'Busiest'];
        foreach ($names as $d => $name) {
            for ($h = 0; $h < 24; $h++) {
                $eve = exp(-((($h - 20) / 3.2) ** 2));
                $day = $d > 4 ? exp(-((($h - 15) / 4.5) ** 2)) : 0;
                $level = (int) min(4, round(($eve + $day * 0.8) * 4 + $r->next() * 0.5));
                $levels[$d][] = $level;
                $say[] = Format::line($name.' '.Dates::hour($h), ', '.$words[$level]);
            }
        }

        return Charts::hours($levels, $say);
    }
}
