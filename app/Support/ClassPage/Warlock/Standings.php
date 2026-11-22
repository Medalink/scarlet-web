<?php

namespace App\Support\ClassPage\Warlock;

use App\Support\ClassPage\Drinking;
use App\Support\ClassPage\StandingFigure;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;

/** The six figures a warlock is compared on, each a number a stranger's page computes the same way. */
final readonly class Standings
{
    private const float HOUR_MS = 3_600_000;

    public function __construct(private ClassRows $rows, private Spells $spells, private Shards $shards, private Demons $demons, private Party $party) {}

    /** @return list<StandingFigure> */
    public function all(): array
    {
        $played = $this->rows->playedMs();
        $fight = $this->rows->states()->fights()->ms;
        $made = $this->shards->made();
        $demon = $this->demons->top();

        return [
            new StandingFigure('shards_hour', 'Soul shards a played hour', $played === 0 ? 0 : $made / ($played / self::HOUR_MS), Unit::Decimal, $this->shards->icon),
            new StandingFigure('taps_fight_hour', 'Life Taps an hour in combat', $fight === 0 ? 0 : $this->spells->lifeTap->fightCasts / ($fight / self::HOUR_MS), icon: Spells::icon($this->spells->lifeTap)),
            new StandingFigure('demon_time', 'Time with a demon out', $played === 0 ? 0 : min(100, $this->demons->withMs() / $played * 100), Unit::Percent, $this->demons->icon($demon)),
            new StandingFigure(Drinking::FIGURE, 'Time spent drinking', Drinking::share($this->rows), Unit::PercentTenth, Drinking::icon($this->rows)),
            new StandingFigure('stones_out', 'Healthstones handed out', $this->party->handedOut(), icon: Spells::icon($this->spells->healthstones)),
            new StandingFigure('shards_destroyed', 'Shards destroyed', $made === 0 ? 0 : min(100, $this->shards->destroyed() / $made * 100), Unit::Percent, $this->shards->icon),
        ];
    }
}
