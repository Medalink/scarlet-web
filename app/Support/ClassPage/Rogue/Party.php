<?php

namespace App\Support\ClassPage\Rogue;

use App\Support\ClassPage\Casts;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\StateKind;

/** What the rogue does for the party: the fights it opened with a Sap, and the scouting spells. */
final readonly class Party
{
    public function __construct(private ClassRows $rows, private Spells $spells, private Openers $openers) {}

    /** @return list<Row> */
    public function rows(): array
    {
        $modules = array_values(array_filter([$this->sap(), $this->scouting()]));

        return $modules === [] ? [] : [new Row($modules)];
    }

    private function sap(): ?Module
    {
        $sap = $this->spells->sap;
        if ($sap->casts === 0) {
            return null;
        }
        $opened = $this->openers->openedWith('Sap');

        return new Module('Sap', [new Pair([
            new PairRow('Casts', $sap->casts),
            new PairRow('Fights opened with one', $opened),
            new PairRow('Of all fights', Casts::share($opened, $this->rows->states(StateKind::Fight)->fights()->starts), Unit::Percent),
        ])], Span::W5, icon: Casts::icon($sap));
    }

    private function scouting(): ?Module
    {
        $rank = Casts::rank(Casts::named($this->rows, Spells::SCOUTING));

        return $rank === null ? null : new Module('Scouting', [$rank], Span::W7);
    }
}
