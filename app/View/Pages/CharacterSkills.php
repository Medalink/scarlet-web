<?php

namespace App\View\Pages;

use App\View\Charts;
use App\View\Format;

/** The profession ranks of a character over its played time, as the step lines the skills module leads with. `classes` gives each line's colour to the bar of the same skill in the rank list, which is the chart's key. */
final class CharacterSkills
{
    private const int LINES = 8;

    private const int MIN_STEPS = 12;

    private const int MAX_STEPS = 41;

    /**
     * @param  list<array{skill: string, points: list<array{0: int, 1: int}>}>  $history  the answer of SkillHistory
     * @param  list<array{0: int, 1: int}>  $buckets  the hours played, as whole hours since the epoch and seconds
     * @return array{spec: array<string, mixed>, classes: array<string, string>, line: array{0: string, 1: string}}|null
     */
    public static function chart(array $history, array $buckets): ?array
    {
        $total = array_sum(array_column($buckets, 1));
        if ($history === [] || $total <= 0) {
            return null;
        }
        usort($history, fn (array $a, array $b): int => [$b['points'][count($b['points']) - 1][1], $a['skill']] <=> [$a['points'][count($a['points']) - 1][1], $b['skill']]);
        $history = array_slice($history, 0, self::LINES);
        $hours = $total / 3600;
        $series = $classes = [];
        foreach ($history as $i => $skill) {
            $class = 'p'.($i + 1);
            $series[] = ['cls' => $class, 'pts' => array_map(fn (array $point): array => [$point[0] / 3600, $point[1]], $skill['points'])];
            $classes[$skill['skill']] = 'c-'.$class;
        }
        $peak = max(array_map(fn (array $skill): int => $skill['points'][count($skill['points']) - 1][1], $history));
        $axis = Axis::nice((float) $peak, 3);
        $ticks = array_values(array_filter(Axis::nice($hours)['ticks'], fn (int|float $tick): bool => $tick <= $hours));
        [$samples, $say] = self::samples($history, $total);

        return [
            'spec' => Charts::steps(['x0' => 0, 'x1' => round($hours, 3), 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks']), 'xt' => Charts::ticks($ticks, 'h')], $series, $samples, $say, count($samples) - 1, 'skills-out'),
            'classes' => $classes,
            'line' => $say[count($say) - 1],
        ];
    }

    /**
     * The ranks at evenly spaced played times: for each, the played hours and the rank of every line, null before its first
     * recorded rank, and the sentence that reads them.
     *
     * @param  list<array{skill: string, points: list<array{0: int, 1: int}>}>  $history
     * @return array{0: list<array{0: float, 1: list<int|null>}>, 1: list<array{0: string, 1: string}>}
     */
    private static function samples(array $history, int $total): array
    {
        $count = max(self::MIN_STEPS, min(self::MAX_STEPS, (int) round($total / 3600) + 1));
        $samples = $say = [];
        for ($i = 0; $i < $count; $i++) {
            $seconds = $total * $i / ($count - 1);
            $ranks = [];
            $parts = [];
            foreach ($history as $skill) {
                $rank = null;
                foreach ($skill['points'] as [$at, $value]) {
                    if ($at > $seconds) {
                        break;
                    }
                    $rank = $value;
                }
                $ranks[] = $rank;
                if ($rank !== null) {
                    $parts[$skill['skill']] = $rank;
                }
            }
            arsort($parts);
            $samples[] = [round($seconds / 3600, 3), $ranks];
            $say[] = Format::line('At '.Format::duration($seconds).' played', $parts === [] ? ': no profession rank recorded yet.' : ': '.implode(', ', array_map(fn (string $skill, int $rank): string => $skill.' '.Format::number($rank), array_keys($parts), array_values($parts))).'.');
        }

        return [$samples, $say];
    }
}
