<?php

namespace App\Support\ClassPage\Rogue;

use App\Support\ClassPage\Casts;
use App\Support\ClassPage\StandingFigure;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\StateKind;

/** The six figures a rogue is compared on, each a rate or a share so characters of different play time compare fairly. */
final readonly class Standings
{
    public function __construct(private ClassRows $rows, private Spells $spells, private Stealth $stealth, private Fighting $fighting) {}

    /** @return list<StandingFigure> */
    public function all(): array
    {
        $played = $this->rows->playedMs();
        $fights = $this->rows->states(StateKind::Fight)->fights()->starts;

        return [
            new StandingFigure('opened_stealth', 'Fights opened from stealth', $this->stealth->openedShare(), Unit::Percent, $this->stealth->icon),
            new StandingFigure('stealth_time', 'Time stealthed, of played time', $this->stealth->timeShare(), Unit::Percent, $this->stealth->icon),
            new StandingFigure('pockets_hour', 'Pockets picked a played hour', Casts::perHour($this->spells->pickPocket->casts, $played), Unit::Decimal, Casts::icon($this->spells->pickPocket)),
            new StandingFigure('kick_landed', 'Kicks that landed', $this->fighting->landedShare(), Unit::Percent, Casts::icon($this->spells->kick)),
            new StandingFigure('vanish_fights', 'Vanishes a hundred fights', $fights === 0 ? 0 : $this->spells->vanish->casts / $fights * 100, Unit::Decimal, Casts::icon($this->spells->vanish)),
            new StandingFigure('locks_hour', 'Locks picked a played hour', Casts::perHour($this->spells->pickLock->casts, $played), Unit::Decimal, Casts::icon($this->spells->pickLock)),
        ];
    }
}
