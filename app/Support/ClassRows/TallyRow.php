<?php

namespace App\Support\ClassRows;

/** One tally of `class_tallies` summed over a character: what it keys on (`a`, `b`), how many (`n`), the time some keys add up (`sum`) and the largest of one (`best`). */
final readonly class TallyRow
{
    public function __construct(public int $a, public int $b, public int $n, public int $sum, public int $best) {}
}
