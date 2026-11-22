<?php

namespace App\Support;

/**
 * The grid the world heat is counted on (docs/DESIGN.md, Maps). The world is 3:2, so 768 by 512 cells are square:
 * 1002 by 668 world units make a cell about 1.3 units a side, 20.875 px at the top zoom. The migration
 * `create_world_heat_aggregates` holds the same two numbers in SQL.
 */
final class WorldGrid
{
    public const int COLUMNS = 768;

    public const int ROWS = 512;

    /**
     * The cell of a world position, kept inside the grid.
     *
     * @return array{0: int, 1: int}
     */
    public static function cell(float $u, float $v): array
    {
        return [
            min(self::COLUMNS - 1, max(0, (int) floor($u * self::COLUMNS))),
            min(self::ROWS - 1, max(0, (int) floor($v * self::ROWS))),
        ];
    }

    /**
     * The world rectangle of a cell: u0, v0, u1, v1.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public static function rect(int $i, int $j): array
    {
        return [$i / self::COLUMNS, $j / self::ROWS, ($i + 1) / self::COLUMNS, ($j + 1) / self::ROWS];
    }

    /** The share of cells left out at each end of a column or row range by `around()`. */
    private const float TRIM = 0.02;

    /** The share of a rectangle's size `around()` adds on each side. */
    private const float PADDING = 0.12;

    /** The smallest side of the rectangle `around()` pads, in world widths: a character that never left one spot. */
    private const float MIN_SIDE = 0.02;

    /** The share of a rectangle's size `aroundPoints()` adds on each side. */
    private const float POINT_PADDING = 0.3;

    /**
     * The world rectangle to open a map on so that its points fill it: u0, v0, u1, v1, `MIN_SIDE` at least on each side, with
     * the outer 2 percent of a crowd of 50 points or more left out.
     *
     * @param  list<array{0: float, 1: float}>  $points
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public static function aroundPoints(array $points): ?array
    {
        if ($points === []) {
            return null;
        }
        $us = array_column($points, 0);
        $vs = array_column($points, 1);
        sort($us);
        sort($vs);
        $last = count($points) - 1;
        $low = count($points) >= 50 ? (int) floor(self::TRIM * $last) : 0;
        $high = count($points) >= 50 ? (int) ceil((1 - self::TRIM) * $last) : $last;
        $centreU = ($us[$low] + $us[$high]) / 2;
        $centreV = ($vs[$low] + $vs[$high]) / 2;
        $halfU = max(self::MIN_SIDE, ($us[$high] - $us[$low]) * (1 + 2 * self::POINT_PADDING)) / 2;
        $halfV = max(self::MIN_SIDE * 2 / 3, ($vs[$high] - $vs[$low]) * (1 + 2 * self::POINT_PADDING)) / 2;

        return [max(0.0, $centreU - $halfU), max(0.0, $centreV - $halfV), min(1.0, $centreU + $halfU), min(1.0, $centreV + $halfV)];
    }

    /**
     * The world rectangle to open a map on so that its cells fill it: u0, v0, u1, v1 from the 2nd to the 98th percentile of
     * the cells' columns and rows, which leaves out the odd cell far from the rest, then `PADDING` of its size on each side.
     *
     * @param  list<array<string, mixed>>  $cells  heat rows: cell_i, cell_j
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public static function around(array $cells): ?array
    {
        if ($cells === []) {
            return null;
        }
        $columns = array_map(fn (array $cell): int => (int) $cell['cell_i'], $cells);
        $rows = array_map(fn (array $cell): int => (int) $cell['cell_j'], $cells);
        sort($columns);
        sort($rows);
        $last = count($cells) - 1;
        $low = (int) floor(self::TRIM * $last);
        $high = (int) ceil((1 - self::TRIM) * $last);
        [$u0, $v0] = WorldGrid::rect($columns[$low], $rows[$low]);
        [, , $u1, $v1] = WorldGrid::rect($columns[$high], $rows[$high]);
        $padU = max(self::MIN_SIDE, $u1 - $u0) * self::PADDING;
        $padV = max(self::MIN_SIDE, $v1 - $v0) * self::PADDING;

        return [max(0.0, $u0 - $padU), max(0.0, $v0 - $padV), min(1.0, $u1 + $padU), min(1.0, $v1 + $padV)];
    }
}
