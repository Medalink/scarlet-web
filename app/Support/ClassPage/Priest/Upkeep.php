<?php

namespace App\Support\ClassPage\Priest;

use App\Support\ClassPage\Casts;
use App\Support\ClassPage\Module;
use App\Support\ClassRows\ClassRows;

/** The control and the buffs: casts of each spell from `spell_casts`, the most cast first. */
final readonly class Upkeep
{
    public function __construct(private ClassRows $rows) {}

    public function control(): ?Module
    {
        $rank = Casts::rank(Casts::named($this->rows, Spells::CONTROL));

        return $rank === null ? null : new Module('Control', [$rank]);
    }

    public function buffs(): ?Module
    {
        $rank = Casts::rank(Casts::named($this->rows, Spells::BUFFS));

        return $rank === null ? null : new Module('Buffs kept up', [$rank]);
    }
}
