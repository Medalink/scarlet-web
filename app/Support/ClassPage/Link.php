<?php

namespace App\Support\ClassPage;

/**
 * A flow with no pool: pairs from a left list to a right list, as wide as their counts (opener to finisher, form to
 * form). At most `MOST_SIDE` items on each side and `MOST_SIDE` squared pairs are drawn.
 */
final readonly class Link implements Block
{
    public const int MOST_SIDE = 10;

    /** @var list<LinkItem> */
    public array $left;

    /** @var list<LinkItem> */
    public array $right;

    /** @var list<LinkPair> */
    public array $pairs;

    /**
     * @param  list<LinkItem>  $left
     * @param  list<LinkItem>  $right
     * @param  list<LinkPair>  $pairs
     * @param  string  $unit  what the total counts, written above the chart: "fights"
     */
    public function __construct(array $left, array $right, array $pairs, public string $unit, public string $label)
    {
        $this->left = array_slice($left, 0, self::MOST_SIDE);
        $this->right = array_slice($right, 0, self::MOST_SIDE);
        $this->pairs = array_slice($pairs, 0, self::MOST_SIDE ** 2);
    }
}
