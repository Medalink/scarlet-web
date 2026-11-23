<?php

namespace App\Support\ClassPage\Druid;

use App\Support\ClassPage\StandingFigure;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\StateKind;

/** The six figures a druid is compared on, each a rate or a share a stranger's page computes the same way. */
final readonly class Standings
{
    private const float HOUR_MS = 3_600_000;

    private const int TEN_HOURS = 10;

    public function __construct(private ClassRows $rows, private Spells $spells, private Forms $forms) {}

    /** @return list<StandingFigure> */
    public function all(): array
    {
        $played = $this->rows->playedMs();
        $fights = $this->rows->states(StateKind::Fight)->fights();
        $travel = $this->forms->travelMs();
        $revives = $this->spells->rebirth->casts + $this->spells->innervate->casts;

        return [
            new StandingFigure('shifts_fight', 'Shapeshifts a fight', $fights->starts === 0 ? 0 : $this->forms->shifts / $fights->starts, Unit::Decimal, Forms::icon('moonkin')),
            new StandingFigure('form_share', 'Time in a form of played time', $played === 0 ? 0 : min(100, $this->forms->inFormMs() / $played * 100), Unit::Percent, Forms::icon('bear')),
            new StandingFigure('travel_share', 'Time in Travel Form of travel', $travel === 0 ? 0 : $this->forms->ms('travel') / $travel * 100, Unit::Percent, Forms::icon('travel')),
            new StandingFigure('powershifts', 'Shifts back into the form just left', $this->forms->powershiftShare(), Unit::Percent, Forms::icon('aquatic')),
            new StandingFigure('revives_ten_hours', 'Rebirths and Innervates in '.self::TEN_HOURS.' played hours', $played === 0 ? 0 : $revives / ($played / self::HOUR_MS) * self::TEN_HOURS, Unit::Decimal, Spells::icon($this->spells->innervate)),
            new StandingFigure('feral_fight', 'Fight time in Bear or Cat Form', $fights->ms === 0 ? 0 : min(100, $this->forms->fightMs('bear', 'cat') / $fights->ms * 100), Unit::Percent, Forms::icon('cat')),
        ];
    }
}
