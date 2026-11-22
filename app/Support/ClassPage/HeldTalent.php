<?php

namespace App\Support\ClassPage;

/** A talent of the character's latest build that has a module: the spell's name, the points in it and the most it takes. */
final readonly class HeldTalent
{
    public function __construct(public string $name, public int $spellId, public int $points, public int $max) {}
}
