<?php

namespace App\Support\ClassPage\Mage;

use App\Support\ClassPage\Drinking;
use App\Support\ClassPage\StandingFigure;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\StateKind;

/** The six figures a mage is compared on, each a rate or a share a stranger's page computes the same way. */
final readonly class Standings
{
    private const float HOUR_MS = 3_600_000;

    public function __construct(private ClassRows $rows, private Spells $spells, private Conjuring $conjuring, private Casting $casting) {}

    /** @return list<StandingFigure> */
    public function all(): array
    {
        $played = $this->rows->playedMs();
        $fights = $this->rows->states(StateKind::Fight)->fights();
        $made = $this->conjuring->made();
        $portals = $this->spells->portals->casts + $this->spells->teleports->casts;

        return array_values(array_filter([
            $this->conjuring->shared && $played > 0 ? new StandingFigure('water_hour', 'Water and food conjured a played hour', $made / ($played / self::HOUR_MS), Unit::Decimal, $this->conjuring->icon) : null,
            $this->conjuring->shared && $made > 0 ? new StandingFigure('water_handed', 'Share of what was conjured handed out', min(100, $this->conjuring->handedOut() / $made * 100), Unit::Percent, $this->conjuring->icon) : null,
            new StandingFigure('portals_hour', 'Portals and teleports a played hour', $played === 0 ? 0 : $portals / ($played / self::HOUR_MS), Unit::Decimal, Spells::icon($this->spells->portals)),
            new StandingFigure('big_pulls', 'Fights with '.Casting::BIG.' kills or more', $fights->starts === 0 ? 0 : min(100, $this->casting->bigPulls() / $fights->starts * 100), Unit::Percent, Spells::icon($this->spells->blizzard)),
            Drinking::figure($this->rows),
            new StandingFigure('blinks_hour', 'Blinks an hour in combat', $fights->ms === 0 ? 0 : $this->spells->blink->fightCasts / ($fights->ms / self::HOUR_MS), Unit::Decimal, Spells::icon($this->spells->blink)),
        ]));
    }
}
