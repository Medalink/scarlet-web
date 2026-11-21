<?php

namespace App\View\Design;

use App\View\Charts;

/** Invented heat for the survey map, drawn the way comp.js draws it: clusters of cells that cool toward their edge. */
final class Heat
{
    private const CELL = 83;

    /**
     * Map cells from clusters of [centre x, centre y, spread, count] in map units.
     *
     * @param  list<array{0: int, 1: int, 2: int, 3: int}>  $clusters
     * @return list<array{0: int, 1: int, 2: int}>
     */
    public static function cells(int $seed, array $clusters): array
    {
        $rng = new Rng($seed);
        $cells = [];
        foreach ($clusters as [$cx, $cy, $spread, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $angle = $rng->next() * 6.283;
                $distance = ($rng->next() ** 1.6) * $spread;
                $x = (int) floor(($cx + cos($angle) * $distance) / self::CELL);
                $y = (int) floor(($cy + sin($angle) * $distance * 0.8) / self::CELL);
                $cooling = 1 - $distance / $spread;
                $cells[$x.','.$y] = [$x, $y, $cooling > 0.75 ? 4 : ($cooling > 0.5 ? 3 : ($cooling > 0.25 ? 2 : 1))];
            }
        }

        return array_values($cells);
    }

    /**
     * A map spec with those cells and the death marks.
     *
     * @param  array{x0: int, y0: int, x1: int, y1: int}|null  $box
     * @param  list<array{0: int, 1: int, 2: int, 3: int}>  $clusters
     * @param  list<array{0: int, 1: int}>  $deaths
     * @return array<string, mixed>
     */
    public static function map(int $seed, array $clusters, array $deaths = [], ?array $box = null): array
    {
        return Charts::map(self::cells($seed, $clusters), $deaths, $box);
    }
}
