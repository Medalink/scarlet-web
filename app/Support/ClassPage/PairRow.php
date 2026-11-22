<?php

namespace App\Support\ClassPage;

/** One figure of a pair: a label over a number, and an optional line of small text under it. */
final readonly class PairRow
{
    public function __construct(public string $label, public int|float|string $value, public Unit $unit = Unit::Count, public ?string $small = null) {}
}
