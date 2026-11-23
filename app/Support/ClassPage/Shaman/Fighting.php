<?php

namespace App\Support\ClassPage\Shaman;

use App\Support\ClassPage\Casts;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Meter;
use App\Support\ClassRows\SpellTotals;

/** The shocks and the weapon imbues: casts from `spell_casts`, interrupts from the client's meter. */
final readonly class Fighting
{
    public function __construct(private ClassRows $rows, private Spells $spells) {}

    public function shocks(): int
    {
        return array_sum(array_map(fn (SpellTotals $spell): int => $spell->casts, Casts::named($this->rows, Spells::SHOCKS)));
    }

    public function shocksModule(): ?Module
    {
        $rank = Casts::rank(Casts::named($this->rows, Spells::SHOCKS));
        if ($rank === null) {
            return null;
        }
        $blocks = [$rank];
        $interrupts = $this->rows->meters()->total(Meter::Interrupts);
        if ($interrupts > 0 && $this->spells->earthShock->casts > 0) {
            $blocks[] = new Pair([
                new PairRow('Interrupts landed', $interrupts),
                new PairRow('Of Earth Shocks', Casts::share($interrupts, $this->spells->earthShock->casts), Unit::Percent),
            ]);
        }

        return new Module('Shocks', $blocks);
    }

    public function imbues(): ?Module
    {
        $rank = Casts::rank(Casts::named($this->rows, Spells::IMBUES));

        return $rank === null ? null : new Module('Weapon imbues', [$rank]);
    }
}
