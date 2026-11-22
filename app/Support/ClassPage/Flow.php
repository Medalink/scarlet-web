<?php

namespace App\Support\ClassPage;

/**
 * What was made on the left, one pool in the middle with its total, what it went to on the right, each ribbon as wide
 * as its count. The pool is the sum of the left; when the right does not add up to the same, its ribbons are scaled to
 * the pool so the picture stays whole, and each right item's own count is still the number written beside it.
 */
final readonly class Flow implements Block
{
    /**
     * @param  list<FlowItem>  $left
     * @param  list<FlowItem>  $right
     * @param  string  $unit  what the pool counts, written after its total: "shards"
     * @param  string  $label  what a screen reader hears of the chart
     */
    public function __construct(public array $left, public array $right, public string $unit, public string $label) {}
}
