<?php

namespace App\Support\ClassPage\Paladin;

use App\Support\ClassPage\StandingFigure;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;

/** The six figures a paladin is compared on, each a rate or a share a stranger's page computes the same way. */
final readonly class Standings
{
    private const float HOUR_MS = 3_600_000;

    public function __construct(private ClassRows $rows, private Spells $spells, private Seals $seals, private Combat $combat) {}

    /** @return list<StandingFigure> */
    public function all(): array
    {
        $played = $this->rows->playedMs();
        $hours = $played / self::HOUR_MS;
        $fights = $this->rows->states()->fights()->starts;

        return [
            new StandingFigure('seals_judged', 'Seals judged', $this->seals->judgedShare(), Unit::Percent, Spells::icon($this->spells->judgement)),
            new StandingFigure('judge_fight', 'Judgements a fight', $fights === 0 ? 0 : $this->spells->judgement->casts / $fights, Unit::Decimal, Spells::icon($this->spells->judgement)),
            new StandingFigure('blessings_hour', 'Blessings a played hour', $played === 0 ? 0 : $this->spells->blessingCasts() / $hours, Unit::Decimal),
            new StandingFigure('bubbles_hour', 'Bubbles a played hour', $played === 0 ? 0 : $this->combat->bubbles() / $hours, Unit::Decimal, Spells::icon($this->spells->divineShield)),
            new StandingFigure('loh_hour', 'Lay on Hands a played hour', $played === 0 ? 0 : $this->spells->layOnHands->casts / $hours, Unit::Decimal, Spells::icon($this->spells->layOnHands)),
            new StandingFigure('deaths_hour', 'Deaths a played hour', $played === 0 ? 0 : $this->rows->deaths() / $hours, Unit::Decimal),
        ];
    }
}
