<?php

namespace App\Support\ClassPage;

/** Six figures as a place among characters of the same class in a level bracket. `of` is the class in the plural: "warlocks". The page leaves it null for a bracket under the threshold. */
final readonly class Comparison
{
    /** @param  list<Standing>  $standings */
    public function __construct(public string $of, public int $from, public int $to, public int $characters, public array $standings) {}
}
