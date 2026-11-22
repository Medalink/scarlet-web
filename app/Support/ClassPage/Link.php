<?php

namespace App\Support\ClassPage;

/** A flow with no pool: pairs from a left list to a right list, as wide as their counts (opener to finisher, form to form). */
final readonly class Link implements Block
{
    /**
     * @param  list<LinkItem>  $left
     * @param  list<LinkItem>  $right
     * @param  list<LinkPair>  $pairs
     * @param  string  $unit  what the total counts, written above the chart: "fights"
     */
    public function __construct(public array $left, public array $right, public array $pairs, public string $unit, public string $label) {}
}
