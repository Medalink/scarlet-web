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
}
