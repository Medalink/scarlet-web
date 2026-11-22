<?php

namespace App\Support\ClassPage;

/** How many went from the left item at index `left` to the right item at index `right`. */
final readonly class LinkPair
{
    public function __construct(public int $left, public int $right, public int|float $count) {}
}
