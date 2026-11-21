<?php

namespace App\View\Pages;

use App\Support\XpSource;
use App\View\Charts;
use App\View\Format;

/**
 * The Levels figure of the character page: one bar for the hours in each level, filled by the shares of the experience
 * sources, a tick at the ruleset median wherever at least two characters completed the level, and the deaths of the level
 * in a row under the bars.
 */
final class CharacterLevels
{
    /** Characters that completed a level before the ruleset's median time in it is shown. */
    private const int MEDIAN_FROM = 2;

    /** Smallest share of a level's experience its readout line names. */
    private const float SAID_FROM = 0.05;

    /** Sources a readout line names. */
    private const int SAID = 3;

    /** The class of each source in a key. */
    private const array CLASSES = [
        'k' => 'c-kill', 'd' => 'c-dungeon', 'q' => 'c-quest', 'e' => 'c-explore', 'o' => 'c-other',
    ];

    /**
     * @param  array{character: list<array{level: int, seconds: int}>, ruleset: list<array<string, mixed>>}  $times  the levels the character has left and the ruleset's time in each
     * @param  list<array<string, mixed>>  $xp  one row for each level and source: level, source, xp
     * @param  array<int, int>  $deaths  the deaths at each level
     * @return array{spec: array<string, mixed>, line: array{0: string, 1: string}, key: list<array<string, mixed>>}|null
     */
    public static function figure(array $times, array $xp, array $deaths): ?array
    {
        $own = array_column($times['character'], 'seconds', 'level');
        if (count($own) < 2) {
            return null;
        }
        $medians = [];
        foreach ($times['ruleset'] as $row) {
            if ((int) $row['completed'] >= self::MEDIAN_FROM) {
                $medians[(int) $row['level']] = (float) $row['median'] / 3600;
            }
        }
        $shares = self::shares($xp);
        $levels = [...array_keys($own), ...array_keys($deaths)];
        $first = min($levels);
        $last = max($levels);
        $data = $say = [];
        $slowest = 0;
        $sources = [];
        for ($level = $first; $level <= $last; $level++) {
            $hours = isset($own[$level]) ? $own[$level] / 3600 : null;
            $median = $hours === null ? null : ($medians[$level] ?? null);
            $row = [$level, $hours, $shares[$level] ?? null, $median, $deaths[$level] ?? 0];
            $slowest = $hours !== null && $hours > ($data[$slowest][1] ?? 0) ? count($data) : $slowest;
            $data[] = $row;
            $say[] = self::line($row);
            foreach ($row[2] ?? [] as $i => $share) {
                $sources[$i] = ($sources[$i] ?? false) || $share > 0;
            }
        }
        $tallest = max([0.0, ...array_filter(array_column($data, 1), fn (mixed $hours): bool => $hours !== null), ...array_filter(array_column($data, 3), fn (mixed $hours): bool => $hours !== null)]);
        $minutes = $tallest < 1;
        $scale = $minutes ? 60 : 1;
        $axis = Axis::nice($tallest * $scale);
        $drawn = array_map(fn (array $row): array => [$row[0], $row[1] === null ? null : $row[1] * $scale, $row[2], $row[3] === null ? null : $row[3] * $scale, $row[4]], $data);

        return [
            'spec' => Charts::levels(
                ['w' => 1100, 'h' => 300, 'x0' => $first - 1, 'x1' => $last + 1, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks'], $minutes ? 'm' : 'h'), 'xt' => Charts::ticks(Axis::spread($first, $last))],
                $drawn, $say, $slowest, 'levels-out',
            ),
            'line' => $say[$slowest],
            'key' => [
                ...self::sourceKey($sources),
                ...array_filter(array_column($data, 3), fn (mixed $hours): bool => $hours !== null) === [] ? [] : [['label' => 'Ruleset median', 'class' => 'k-line', 'style' => 'background:var(--fg)']],
                ...array_sum(array_column($data, 4)) === 0 ? [] : [['label' => 'Deaths', 'class' => 'k-cross']],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $xp
     * @return array<int, list<float>> the shares of each level's experience, in the order of XpSource
     */
    private static function shares(array $xp): array
    {
        $byLevel = [];
        foreach ($xp as $row) {
            $byLevel[(int) $row['level']][(string) $row['source']] = (float) $row['xp'];
        }
        $shares = [];
        foreach ($byLevel as $level => $sources) {
            $total = array_sum($sources);
            if ($total <= 0) {
                continue;
            }
            $shares[$level] = array_map(fn (XpSource $source): float => round(($sources[$source->value] ?? 0.0) / $total, 3), XpSource::cases());
        }

        return $shares;
    }

    /**
     * @param  array<int, bool>  $present
     * @return list<array<string, mixed>>
     */
    private static function sourceKey(array $present): array
    {
        $key = [];
        foreach (XpSource::cases() as $i => $source) {
            if ($present[$i] ?? false) {
                $key[] = ['label' => ucfirst($source->label()), 'class' => self::CLASSES[$source->value]];
            }
        }

        return $key;
    }

    /**
     * @param  array{0: int, 1: float|null, 2: list<float>|null, 3: float|null, 4: int}  $row
     * @return array{0: string, 1: string}
     */
    private static function line(array $row): array
    {
        [$level, $hours, $shares, $median, $deaths] = $row;
        $parts = [];
        if ($hours !== null) {
            $parts[] = Format::duration($hours * 3600).($median === null ? '' : ', median '.Format::duration($median * 3600));
        }
        if ($hours === null && $shares !== null) {
            $parts[] = 'time not counted';
        }
        $experience = $shares === null ? null : self::experience($shares);
        if ($experience !== null) {
            $parts[] = $experience;
        }
        if ($deaths > 0) {
            $parts[] = Format::count($deaths, 'death');
        }

        return Format::line('Level '.$level, $parts === [] ? ': nothing recorded.' : ': '.implode('; ', $parts).'.');
    }

    /** @param  list<float>  $shares */
    private static function experience(array $shares): ?string
    {
        $named = array_combine(array_map(fn (XpSource $source): string => $source->label(), XpSource::cases()), $shares);
        arsort($named);
        $named = array_slice(array_filter($named, fn (float $share): bool => $share >= self::SAID_FROM), 0, self::SAID);

        return $named === [] ? null : 'experience from '.implode(', ', array_map(fn (string $name, float $share): string => $name.' '.Format::percent($share), array_keys($named), $named));
    }
}
