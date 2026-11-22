<?php

namespace App\Support\ClassPage;

/** Label and number pairs side by side, three to a line. */
final readonly class Pair implements Block
{
    /** @param  list<PairRow>  $rows */
    public function __construct(public array $rows) {}
}
