<?php

namespace App\Support;

/**
 * Puts the cells a character walked in the order it first walked them, for a replay. The heat aggregate keeps a day
 * at a time, so the days are the recorded order; within a day each cell follows the nearest cell left, starting from
 * where the day before ended, so a day reads as one walk. A day with more cells than `CHAIN` keeps its order.
 */
final class WalkOrder
{
    /** Cells of one day ordered by distance at most; the search compares every pair. */
    private const int CHAIN = 1500;

    /**
     * @param  list<array{0: int, 1: int, 2: int}>  $cells  cell_i, cell_j and the points counted
     * @param  list<string>  $firstDays  the UTC day each cell was first walked, in the order of $cells
     * @return array{cells: list<array{0: int, 1: int, 2: int}>, days: list<array{0: string, 1: int}>} the cells oldest first and the cells first walked on each day
     */
    public static function arrange(array $cells, array $firstDays): array
    {
        /** @var array<string, non-empty-list<array{0: int, 1: int, 2: int}>> $byDay */
        $byDay = [];
        foreach ($cells as $n => $cell) {
            $byDay[$firstDays[$n]][] = $cell;
        }
        ksort($byDay);
        $ordered = $days = [];
        $end = null;
        foreach ($byDay as $day => $group) {
            $group = count($group) <= self::CHAIN ? self::chain($group, $end) : $group;
            $end = $group[count($group) - 1];
            $days[] = [(string) $day, count($group)];
            array_push($ordered, ...$group);
        }

        return ['cells' => $ordered, 'days' => $days];
    }

    /**
     * @param  non-empty-list<array{0: int, 1: int, 2: int}>  $left
     * @param  array{0: int, 1: int, 2: int}|null  $from
     * @return non-empty-list<array{0: int, 1: int, 2: int}>
     */
    private static function chain(array $left, ?array $from): array
    {
        $path = [];
        $here = $from;
        if ($here === null) {
            $here = array_shift($left);
            $path = [$here];
        }
        while ($left !== []) {
            $best = 0;
            $gap = PHP_INT_MAX;
            foreach ($left as $k => $cell) {
                $distance = ($cell[0] - $here[0]) ** 2 + ($cell[1] - $here[1]) ** 2;
                if ($distance < $gap) {
                    $gap = $distance;
                    $best = $k;
                }
            }
            $here = $left[$best];
            $path[] = $here;
            array_splice($left, $best, 1);
        }

        /** @var non-empty-list<array{0: int, 1: int, 2: int}> $path */
        return $path;
    }
}
