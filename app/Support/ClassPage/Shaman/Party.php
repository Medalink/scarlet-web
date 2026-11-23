<?php

namespace App\Support\ClassPage\Shaman;

use App\Support\ClassPage\Casts;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Meter;
use App\Support\ClassRows\SpellTotals;

/** What the shaman does for other players: the totems that stand for the group, the heals and raises, the purges and cures. */
final readonly class Party
{
    public function __construct(private ClassRows $rows, private Totems $totems) {}

    /** @return list<Row> */
    public function rows(): array
    {
        $modules = array_values(array_filter([$this->group(), $this->heals(), $this->cures()]));

        return $modules === [] ? [] : [new Row($modules)];
    }

    private function group(): ?Module
    {
        $rank = $this->totems->hours();

        return $rank === null ? null : new Module('Totems for the group', [$rank]);
    }

    private function heals(): ?Module
    {
        $rank = Casts::rank(Casts::named($this->rows, Spells::HEALS));

        return $rank === null ? null : new Module('Heals and raises', [$rank]);
    }

    private function cures(): ?Module
    {
        $spells = Casts::named($this->rows, Spells::CURES);
        $rank = Casts::rank($spells);
        if ($rank === null) {
            return null;
        }
        $casts = array_sum(array_map(fn (SpellTotals $spell): int => $spell->casts, $spells));
        $landed = $this->rows->meters()->total(Meter::Dispels);
        $cells = [new PairRow('Casts', $casts)];
        if ($landed > 0) {
            $cells[] = new PairRow('Dispels landed', $landed);
            $cells[] = new PairRow('Landed', Casts::share($landed, $casts), Unit::Percent);
        }

        return new Module('Purges and cures', [$rank, new Pair($cells)]);
    }
}
