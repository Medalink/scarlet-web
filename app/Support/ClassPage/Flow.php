<?php

namespace App\Support\ClassPage;

/**
 * What was made on the left, one pool in the middle with its total, what it went to on the right, each ribbon as wide
 * as its count. The pool is the sum of the left; when the right does not add up to the same, its ribbons are scaled to
 * the pool so the picture stays whole, and each right item's own count is still the number written beside it. At most
 * `MOST_LEFT` sources and `MOST_RIGHT` uses are drawn.
 */
final readonly class Flow implements Block
{
    public const int MOST_LEFT = 8;

    public const int MOST_RIGHT = 12;

    /** @var list<FlowItem> */
    public array $left;

    /** @var list<FlowItem> */
    public array $right;

    /**
     * @param  list<FlowItem>  $left
     * @param  list<FlowItem>  $right
     * @param  string  $unit  what the pool counts, written after its total: "shards"
     * @param  string  $label  what a screen reader hears of the chart
     */
    public function __construct(array $left, array $right, public string $unit, public string $label)
    {
        $this->left = array_slice($left, 0, self::MOST_LEFT);
        $this->right = array_slice($right, 0, self::MOST_RIGHT);
    }
}
