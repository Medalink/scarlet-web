<?php

namespace App\Support\ClassPage;

use App\Models\Character;
use App\Support\ClassRows\ClassRows;
use App\Support\PlayerClass;

/** What one read of a character's class gave: the class, the reader it used, the facts it knew and what the builder made of them. */
final readonly class ClassRead
{
    public function __construct(
        public Character $character,
        public PlayerClass $class,
        public ClassRows $rows,
        public ClassFacts $facts,
        public ClassParts $parts,
    ) {}
}
