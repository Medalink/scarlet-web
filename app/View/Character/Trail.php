<?php

namespace App\View\Character;

/** The route card of a character: every cell of the world it walked, oldest first, fitted to one sheet for `resources/js/sc/route.js`. */
final class Trail
{
    private const int FINE_COLUMNS = 1536;

    private const int FINE_ROWS = 1024;

    private const int CELLS = 2500;

    /**
     * @param  array{cells: list<array{0: int, 1: int, 2: int}>, fit: array{0: float, 1: float, 2: float, 3: float}|null}  $heat
     * @param  list<array{kind: string, u: float, v: float}>  $marks
     * @return array<string, mixed>|null
     */
    public static function spec(array $heat, array $marks): ?array
    {
        if ($heat['cells'] === [] || $heat['fit'] === null) {
            return null;
        }
        [$u0, $v0, $u1, $v1] = $heat['fit'];
        $du = max(1e-6, $u1 - $u0);
        $dv = max(1e-6, $v1 - $v0);
        $aspect = max(0.4, min(2.5, $du * 3 / ($dv * 2)));
        $width = $aspect >= 1 ? 1000.0 : 1000.0 * $aspect;
        $height = $aspect >= 1 ? 1000.0 / $aspect : 1000.0;
        $place = fn (float $u, float $v): array => [round(($u - $u0) / $du * $width, 1), round(($v1 - $v) / $dv * $height, 1)];
        $stride = max(1, (int) ceil(count($heat['cells']) / self::CELLS));
        $cells = [];
        foreach ($heat['cells'] as $i => $cell) {
            if ($i % $stride === 0) {
                array_push($cells, ...$place(($cell[0] + 0.5) / self::FINE_COLUMNS, ($cell[1] + 0.5) / self::FINE_ROWS));
            }
        }
        $deaths = [];
        foreach ($marks as $mark) {
            if ($mark['kind'] === 'death' && $mark['u'] >= $u0 && $mark['u'] <= $u1 && $mark['v'] >= $v0 && $mark['v'] <= $v1) {
                $deaths[] = $place($mark['u'], $mark['v']);
            }
        }

        return ['aspect' => round($aspect, 3), 'cells' => $cells, 'cell' => round($stride * $width / ($du * self::FINE_COLUMNS), 2), 'deaths' => array_slice($deaths, 0, 12)];
    }
}
