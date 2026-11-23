<?php

namespace App\Support\ClassPage\Shaman;

use App\Support\ClassPage\Casts;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\StandingFigure;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\StateKind;

/** The six figures a shaman is compared on, each a rate or a share so characters of different play time compare fairly. */
final readonly class Standings
{
    public function __construct(private ClassRows $rows, private Spells $spells, private Totems $totems, private Fighting $fighting, private Walks $walks) {}

    /** @return list<StandingFigure> */
    public function all(): array
    {
        $fights = $this->rows->states(StateKind::Fight)->fights()->starts;
        $played = $this->rows->playedMs();
        $top = $this->totems->top();
        $totem = $top === null ? null : Icon::spell($top['ref']);
        $deaths = $this->rows->deaths();

        return [
            new StandingFigure('totems_fight', 'Totems dropped a fight', Casts::perFight($this->totems->drops(), $fights), Unit::Decimal, $totem),
            new StandingFigure('four_down', 'Fights with all four down', $this->totems->fourDownShare(), Unit::Percent, $totem),
            new StandingFigure('shocks_fight', 'Shocks a fight', Casts::perFight($this->fighting->shocks(), $fights), Unit::Decimal, Casts::icon($this->spells->earthShock)),
            new StandingFigure('wolf_time', 'Time as a Ghost Wolf, of played time', Casts::share($this->walks->wolfHours() * 3_600_000, $played), Unit::Percent, $this->walks->wolfIcon()),
            new StandingFigure('reincarnations_deaths', 'Reincarnations a hundred deaths', $deaths === 0 ? 0 : $this->spells->reincarnation->casts / $deaths * 100, Unit::Percent, Casts::icon($this->spells->reincarnation)),
            new StandingFigure('recalls_hour', 'Astral Recalls a played hour', Casts::perHour($this->spells->recall->casts, $played), Unit::Decimal, Casts::icon($this->spells->recall)),
        ];
    }
}
