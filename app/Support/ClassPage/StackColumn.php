<?php

namespace App\Support\ClassPage;

/** One level, or level band, of a stack chart: each series' share of it, 0 to 1, in the order of the chart's series. */
final readonly class StackColumn
{
    /** @param  list<int|float>  $shares */
    public function __construct(public int|string $label, public array $shares) {}
}
