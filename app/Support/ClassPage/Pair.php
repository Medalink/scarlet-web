<?php

namespace App\Support\ClassPage;

/** Label and number pairs side by side, three to a line, at most `MOST_ROWS`. */
final readonly class Pair implements Block
{
    public const int MOST_ROWS = 12;

    /** @var list<PairRow> */
    public array $rows;

    /** @param  list<PairRow>  $rows */
    public function __construct(array $rows)
    {
        $this->rows = array_slice($rows, 0, self::MOST_ROWS);
    }
}
