<?php

namespace App\Support\ClassPage\Priest;

use App\Support\ClassPage\Casts;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\StandingFigure;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;

/** The six figures a priest is compared on, each a rate or a share so characters of different play time compare fairly. */
final readonly class Standings
{
    public function __construct(private ClassRows $rows, private Spells $spells, private Healing $healing, private Shadow $shadow) {}

    /** @return list<StandingFigure> */
    public function all(): array
    {
        $played = $this->rows->playedMs();
        $fights = $this->rows->states()->fights()->starts;

        return [
            new StandingFigure('healing_fight', 'Healing a fight', $this->healing->perFight(), Unit::Count, $this->icon()),
            new StandingFigure('shields_fight', 'Shields a fight', Casts::perFight($this->spells->shield->casts, $fights), Unit::Decimal, Casts::icon($this->spells->shield)),
            new StandingFigure('raised_hour', 'Players raised a played hour', Casts::perHour($this->spells->resurrection->casts, $played), Unit::Decimal, Casts::icon($this->spells->resurrection)),
            new StandingFigure('shadowform_time', 'Time in Shadowform', $this->shadow->formShare(), Unit::Percent, $this->shadow->formIcon()),
            new StandingFigure('wand_share', 'Wand share of fight time', $this->shadow->wandShare(), Unit::Percent, $this->shadow->wandIcon()),
            new StandingFigure('deaths_hour', 'Deaths a played hour', Casts::perHour($this->rows->deaths(), $played), Unit::Decimal),
        ];
    }

    private function icon(): ?Icon
    {
        $top = $this->healing->top();

        return $top === null ? null : Casts::icon($top);
    }
}
