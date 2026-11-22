<?php

namespace App\Support\ClassPage;

use InvalidArgumentException;

/** The talent build as the name of the tree with the most points and the points in each of the class's three trees. */
final readonly class Build
{
    /** @param  list<int>  $points */
    public function __construct(public string $tree, public array $points)
    {
        if (count($points) !== 3) {
            throw new InvalidArgumentException('A build has points in three trees.');
        }
    }
}
