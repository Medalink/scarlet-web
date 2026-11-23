<?php

namespace App\Support\ClassPage\Warrior;

use App\Support\ClassPage\StandingFigure;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;

/** The six figures a warrior is compared on, each a rate or a share a stranger's page computes the same way. */
final readonly class Standings
{
    private const float HOUR_MS = 3_600_000;

    public function __construct(private ClassRows $rows, private Spells $spells, private Stances $stances, private Shouts $shouts, private Attacks $attacks, private Damage $damage) {}

    /** @return list<StandingFigure> */
    public function all(): array
    {
        $played = $this->rows->playedMs();
        $kills = $this->attacks->kills();

        return [
            new StandingFigure('charges_hour', 'Charges a played hour', $played === 0 ? 0 : $this->spells->charges->casts / ($played / self::HOUR_MS), Unit::Decimal, Spells::icon($this->spells->charges)),
            new StandingFigure('execute_kills', 'Executes of kills', $kills === 0 ? 0 : min(100, $this->attacks->executeKills() / $kills * 100), Unit::Percent, Spells::icon($this->spells->execute)),
            new StandingFigure('defensive_time', 'Time in Defensive Stance', $this->stances->fightShare('Defensive Stance'), Unit::Percent),
            new StandingFigure('shout_fights', 'Battle Shout up in fights', $this->shouts->upShare(), Unit::Percent, Spells::icon($this->spells->battleShout)),
            new StandingFigure('taken_fight', 'Damage taken a fight', $this->damage->takenPerFight()),
            new StandingFigure('deaths_hour', 'Deaths a played hour', $played === 0 ? 0 : $this->rows->deaths() / ($played / self::HOUR_MS), Unit::Decimal),
        ];
    }
}
