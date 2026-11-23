<?php

namespace App\Support\ClassPage\Paladin;

use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\ClassTable;
use App\Support\ClassRows\Meter;
use App\Support\ClassRows\SpellTotals;

/** What the paladin does for the party: blessings, saves and cleansing. */
final readonly class Party
{
    public function __construct(private ClassRows $rows, private Spells $spells, private Blessings $blessings) {}

    /** @return list<Row> */
    public function rows(): array
    {
        $modules = array_values(array_filter([$this->blessings(), $this->saves(), $this->cleansing()]));

        return $modules === [] ? [] : [new Row($modules)];
    }

    private function blessings(): ?Module
    {
        $all = $this->spells->blessingCasts();
        if ($all === 0) {
            return null;
        }
        $played = $this->rows->playedMs();
        $cells = [
            new PairRow('Blessings a played hour', $played === 0 ? 0 : $all / ($played / 3_600_000), Unit::Decimal),
            new PairRow('Greater blessings', $this->spells->greater->casts),
        ];
        if ($this->rows->holds(ClassTable::Items)) {
            $cells[] = new PairRow('Symbols of Kings used', $this->blessings->symbols());
        }

        return new Module('Blessings for the group', [new Pair($cells)], note: 'The rows hold no group time, so the rate is over the time played.');
    }

    private function saves(): ?Module
    {
        $cast = array_values(array_filter($this->rows->spells()->named(...Spells::SAVES), fn (SpellTotals $spell): bool => $spell->casts > 0));
        if ($cast === []) {
            return null;
        }

        return new Module('Saves', [new Rank(array_map(fn (SpellTotals $spell): RankRow => new RankRow($spell->name, $spell->casts, Spells::icon($spell), Tone::Own), $cast))]);
    }

    private function cleansing(): ?Module
    {
        $cleanse = $this->rows->spells()->sum('Cleanse', 'Cleanse');
        $purify = $this->rows->spells()->sum('Purify', 'Purify');
        $landed = $this->rows->meters()->total(Meter::Dispels);
        if ($cleanse->casts + $purify->casts + $landed === 0) {
            return null;
        }

        return new Module('Cleansing', [
            new Pair([new PairRow('Cleanse', $cleanse->casts), new PairRow('Purify', $purify->casts), new PairRow('Dispels landed', $landed)]),
        ], note: 'Landed is the client\'s dispel meter, which counts every dispel of yours.');
    }
}
