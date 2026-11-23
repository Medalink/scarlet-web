<?php

namespace App\Support\ClassPage\Mage;

use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Meter;
use App\Support\ClassRows\SpellTotals;

/** What the mage does for the party: the water and food handed out, Arcane Intellect and the curses lifted. */
final readonly class Party
{
    public function __construct(private ClassRows $rows, private Spells $spells, private Conjuring $conjuring) {}

    /** @return list<Row> */
    public function rows(): array
    {
        $modules = array_values(array_filter([$this->handedOut(), $this->intellect(), $this->curses()]));

        return $modules === [] ? [] : [new Row($modules)];
    }

    private function handedOut(): ?Module
    {
        $handed = $this->conjuring->handedOut();
        if ($handed === 0) {
            return null;
        }
        $share = $this->conjuring->made() === 0 ? 0 : min(100, $handed / $this->conjuring->made() * 100);

        return new Module('Water and food handed out', [
            new Rank([
                new RankRow('Water', $this->conjuring->handedWater(), $this->conjuring->icon, Tone::Own),
                new RankRow('Food', $this->conjuring->handedFood(), $this->conjuring->foodIcon(), Tone::Own),
            ]),
            new Pair([new PairRow('Handed out', $handed), new PairRow('Of what was conjured', $share, Unit::Percent)]),
        ], note: 'Who took them is not recorded.');
    }

    private function intellect(): ?Module
    {
        $intellect = $this->spells->intellect;
        if ($intellect->casts === 0) {
            return null;
        }
        $kinds = array_values(array_filter($this->rows->spells()->named('Arcane Intellect', 'Arcane Brilliance'), fn (SpellTotals $spell): bool => $spell->casts > 0));
        $hours = $this->rows->playedMs() / 3_600_000;

        return new Module('Arcane Intellect', [
            new Rank(array_map(fn (SpellTotals $spell): RankRow => new RankRow($spell->name, $spell->casts, Spells::icon($spell), Tone::Own), $kinds)),
            new Pair([new PairRow('Casts', $intellect->casts), new PairRow('Casts a played hour', $hours <= 0 ? 0 : $intellect->casts / $hours, Unit::Decimal)]),
        ], icon: Spells::icon($intellect));
    }

    private function curses(): ?Module
    {
        $remove = $this->spells->removeCurse;
        if ($remove->casts === 0) {
            return null;
        }

        return new Module('Curses lifted', [new Pair([
            new PairRow($remove->name.' casts', $remove->casts),
            new PairRow('Curses lifted', $this->rows->meters()->total(Meter::Dispels)),
            new PairRow('In a fight', $remove->fightCasts / $remove->casts * 100, Unit::Percent),
        ])], icon: Spells::icon($remove));
    }
}
