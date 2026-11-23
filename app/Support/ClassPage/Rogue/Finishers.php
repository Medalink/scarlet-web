<?php

namespace App\Support\ClassPage\Rogue;

use App\Support\ClassPage\Casts;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Span;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\ClassTable;
use App\Support\ClassRows\SpellTotals;

/** The finisher cast at each level: the five finishers' casts by level, every rank counted as one spell. */
final readonly class Finishers
{
    public function __construct(private ClassRows $rows, private Spells $spells) {}

    public function module(): ?Module
    {
        $stack = Casts::stack($this->spells->finishers, $this->rows->levels(ClassTable::Casts), array_values(Spells::FINISHERS), 'finishers cast');
        if ($stack === null) {
            return null;
        }

        return new Module('The finisher used, by level', [$stack], Span::W7, $this->note());
    }

    private function note(): ?string
    {
        $first = Casts::firstLevels($this->spells->finishers);
        $names = array_keys($first);
        $levels = array_values($first);
        if (count($names) < 2 || $levels[1] <= $levels[0]) {
            return null;
        }

        return $names[0].' was the only finisher before level '.$levels[1].', and '.$names[1].' came in at level '.$levels[1].'.';
    }

    /** Finishers cast, all five together. */
    public function casts(): int
    {
        return array_sum(array_map(fn (SpellTotals $spell): int => $spell->casts, $this->spells->finishers));
    }
}
