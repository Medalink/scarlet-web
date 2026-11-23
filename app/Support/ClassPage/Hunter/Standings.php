<?php

namespace App\Support\ClassPage\Hunter;

use App\Support\ClassPage\StandingFigure;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\StateKind;

/** The six figures a hunter is compared on, each a rate or a share a stranger's page computes the same way. */
final readonly class Standings
{
    private const float HOUR_MS = 3_600_000;

    public function __construct(private ClassRows $rows, private Pets $pets, private Ammo $ammo, private Shooting $shooting, private Field $field) {}

    /** @return list<StandingFigure> */
    public function all(): array
    {
        $fights = $this->rows->states(StateKind::Fight)->fights()->starts;
        $played = $this->rows->playedMs();

        return [
            new StandingFigure('pet_time', 'Time with a pet out', $this->pets->share(), Unit::Percent),
            new StandingFigure('pet_happy', 'Pet happy while out', $this->pets->happy(), Unit::Percent),
            new StandingFigure('shots_fight', 'Shots a fight', $fights === 0 ? 0 : $this->ammo->fired() / $fights, Unit::Decimal),
            new StandingFigure('autoshot_share', 'Auto Shot of fight time', $this->shooting->autoShotShare(), Unit::Percent),
            new StandingFigure('feign_worked', 'Feign Deaths that worked', $this->field->feignWorked(), Unit::Percent),
            new StandingFigure('deaths_hour', 'Deaths a played hour', $played === 0 ? 0 : $this->rows->deaths() / ($played / self::HOUR_MS), Unit::Decimal),
        ];
    }
}
