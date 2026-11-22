<?php

namespace App\Support;

/**
 * The cell sizes heat is kept at (docs/DESIGN.md, Maps). Level `n` is `192 * 2^n` columns by `128 * 2^n` rows over the 3:2
 * world, so a cell is square and a cell of level `n` is four cells of level `n + 1`.
 */
final class HeatGrid
{
    public const int LEVELS = 4;

    public const int BASE_COLUMNS = 192;

    public const int BASE_ROWS = 128;

    /** The level the older `/public/map/heat` answers in: the 768 by 512 grid. */
    public const int LEGACY_LEVEL = 2;

    public static function columns(int $level): int
    {
        return self::BASE_COLUMNS << $level;
    }

    public static function rows(int $level): int
    {
        return self::BASE_ROWS << $level;
    }

    public static function level(int $columns): int
    {
        return (int) round(log($columns / self::BASE_COLUMNS, 2));
    }
}
