<?php

namespace App\View\Pages;

/** The hero map of a character page with what resources/js/replay.js needs to draw its walk again in order. */
final class CharacterReplay
{
    /**
     * A character whose cells carry their order gets `replay`: the cells first walked on each day, and on every death mark the
     * index of the day it died on (`d`), so a mark shows once the replay reaches the end of that day.
     *
     * @param  array{cells: list<array{0: int, 1: int, 2: int}>, fit: array{0: float, 1: float, 2: float, 3: float}|null, days?: list<array{0: string, 1: int}>}  $heat
     * @param  list<array{kind: string, u: float, v: float, day: string}>  $marks
     * @param  string  $cellsUrl  where the viewer reads the cells after the first paint
     * @return array<string, mixed>
     */
    public static function map(array $heat, array $marks, string $cellsUrl): array
    {
        $days = $heat['days'] ?? [];
        if ($days === [] || array_sum(array_column($days, 1)) !== count($heat['cells'])) {
            return MapSpec::remote($cellsUrl, $heat['fit'], ['look' => MapSpec::SURVEY], array_map(fn (array $mark): array => ['kind' => $mark['kind'], 'u' => $mark['u'], 'v' => $mark['v']], $marks));
        }
        $dates = array_column($days, 0);
        $placed = array_map(fn (array $mark): array => [
            'kind' => $mark['kind'], 'u' => $mark['u'], 'v' => $mark['v'],
            'd' => max(0, count(array_filter($dates, fn (string $date): bool => $date <= $mark['day'])) - 1),
        ], $marks);

        return MapSpec::remote($cellsUrl, $heat['fit'], ['look' => MapSpec::SURVEY, 'replay' => ['days' => array_column($days, 1)]], $placed);
    }
}
