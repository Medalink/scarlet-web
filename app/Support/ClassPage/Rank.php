<?php

namespace App\Support\ClassPage;

/**
 * Rows with a bar each, at most `MOST_ROWS`. A bar with no tone is scarlet, or white in a `plain` rank. `of` is the
 * value a full bar stands for, the largest row when left out.
 */
final readonly class Rank implements Block
{
    public const int MOST_ROWS = 20;

    /** @var list<RankRow> */
    public array $rows;

    /** @param  list<RankRow>  $rows */
    public function __construct(array $rows, public Unit $unit = Unit::Count, public bool $plain = false, public int|float|null $of = null)
    {
        $this->rows = array_slice($rows, 0, self::MOST_ROWS);
    }
}
