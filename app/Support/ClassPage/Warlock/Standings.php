<?php

namespace App\Support\ClassPage\Warlock;

use App\Support\ClassPage\Drinking;
use App\Support\ClassPage\StandingFigure;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\ClassTable;
use App\Support\StateKind;

/**
 * The six figures a warlock is compared on, each a number a stranger's page computes the same way. A figure whose
 * rows the character does not share (no item flows, no pet rows) or whose whole is 0 (no time played, no fight) is
 * left out, so it is neither stored nor drawn as a place.
 */
final readonly class Standings
{
    private const float HOUR_MS = 3_600_000;

    public function __construct(private ClassRows $rows, private Spells $spells, private Shards $shards, private Demons $demons, private Party $party) {}

    /** @return list<StandingFigure> */
    public function all(): array
    {
        $items = $this->rows->playedMs(ClassTable::Items);
        $fight = $this->rows->states(StateKind::Fight)->fights()->ms;
        $made = $this->shards->made();
        $demon = $this->demons->top();

        return array_values(array_filter([
            $this->shards->shared && $items > 0 ? new StandingFigure('shards_hour', 'Soul shards a played hour', $made / ($items / self::HOUR_MS), Unit::Decimal, $this->shards->icon) : null,
            $fight > 0 ? new StandingFigure('taps_fight_hour', 'Life Taps an hour in combat', $this->spells->lifeTap->fightCasts / ($fight / self::HOUR_MS), icon: Spells::icon($this->spells->lifeTap)) : null,
            $this->demons->shared() && $this->demons->playedMs() > 0 ? new StandingFigure('demon_time', 'Time with a demon out', min(100, $this->demons->withMs() / $this->demons->playedMs() * 100), Unit::Percent, $this->demons->icon($demon)) : null,
            Drinking::figure($this->rows),
            $this->party->shared() ? new StandingFigure('stones_out', 'Healthstones handed out', $this->party->handedOut(), icon: Spells::icon($this->spells->healthstones)) : null,
            $this->shards->shared && $made > 0 ? new StandingFigure('shards_destroyed', 'Shards destroyed', min(100, $this->shards->destroyed() / $made * 100), Unit::Percent, $this->shards->icon) : null,
        ]));
    }
}
