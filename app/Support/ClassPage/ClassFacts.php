<?php

namespace App\Support\ClassPage;

use App\Support\PlayerClass;
use App\Support\TalentTree;

/**
 * What a builder knows of the character beyond the class tables: who it is, its latest build and the talents of that
 * build that have a module (`TalentModules`, at most three, in the build's order), and the stored standings of the
 * class that its comparisons read.
 */
final readonly class ClassFacts
{
    /** @param  list<HeldTalent>  $talents */
    public function __construct(
        public PlayerClass $class,
        public string $name,
        public int $level,
        public int|float $hours,
        public ?Build $build,
        public ?TalentTree $tree,
        public array $talents,
        public ClassPeers $peers,
    ) {}

    public function holds(string $talent): ?HeldTalent
    {
        foreach ($this->talents as $held) {
            if ($held->name === $talent) {
                return $held;
            }
        }

        return null;
    }
}
