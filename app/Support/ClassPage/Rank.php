<?php

namespace App\Support\ClassPage;

/**
 * Rows with a bar each. A bar with no tone is scarlet, or white in a `plain` rank. `of` is the value a full bar stands
 * for, the largest row when left out.
 */
final readonly class Rank implements Block
{
    /** @param  list<RankRow>  $rows */
    public function __construct(public array $rows, public Unit $unit = Unit::Count, public bool $plain = false, public int|float|null $of = null) {}
}
