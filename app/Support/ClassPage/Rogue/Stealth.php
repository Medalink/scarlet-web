<?php

namespace App\Support\ClassPage\Rogue;

use App\Support\ClassPage\Casts;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Split;
use App\Support\ClassPage\SplitPart;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\StateTotals;
use App\Support\ClassRows\Tally;
use App\Support\StateKind;

/** Time under the Stealth aura and the fights opened out of it: the `a` rows of `state_times` and the `stealth_open` tally. */
final readonly class Stealth
{
    private function __construct(public int $ms, public ?Icon $icon, public int $opened, public int $openedMs, public int $fights, private int $playedMs) {}

    public static function of(ClassRows $rows): self
    {
        $states = [...$rows->states(StateKind::OwnBuff)->of(StateKind::OwnBuff, Spells::STEALTH), ...$rows->states(StateKind::OthersBuff)->of(StateKind::OthersBuff, Spells::STEALTH)];
        usort($states, fn (StateTotals $a, StateTotals $b): int => $b->ms <=> $a->ms);
        $ms = array_sum(array_map(fn (StateTotals $state): int => $state->ms, $states));
        $opened = $openedMs = 0;
        foreach ($rows->tallies()->of(Tally::StealthOpen) as $row) {
            $opened += $row->n;
            $openedMs += $row->sum;
        }

        return new self($ms, $states === [] ? null : Icon::spell($states[0]->refId), $opened, $openedMs, $rows->states(StateKind::Fight)->fights()->starts, $rows->playedMs(StateKind::OwnBuff, StateKind::OthersBuff));
    }

    public function hours(): float
    {
        return $this->ms / 3_600_000;
    }

    /** Hours played from the first level that has aura rows: the time they can say whether the character was stealthed. */
    public function playedHours(): float
    {
        return $this->playedMs / 3_600_000;
    }

    /** Percent of played time under the aura. */
    public function timeShare(): float
    {
        return Casts::share($this->ms, $this->playedMs);
    }

    /** Percent of fights that began with an opener cast out of Stealth. */
    public function openedShare(): float
    {
        return Casts::share($this->opened, $this->fights);
    }

    public function module(): ?Module
    {
        if ($this->ms === 0 && $this->opened === 0) {
            return null;
        }
        $cells = [new PairRow('Hours in stealth', Unit::Hours->say($this->hours()).' h'), new PairRow('Of played time', $this->timeShare(), Unit::Percent)];
        if ($this->opened > 0) {
            $cells[] = new PairRow('Seconds stealthed before the opener', Unit::Decimal->say($this->openedMs / $this->opened / 1000), small: 'average');
        }
        $blocks = [new Pair($cells)];
        if ($this->fights > 0) {
            $share = $this->openedShare();
            $blocks[] = new Split([
                new SplitPart('Fights opened from stealth, '.Unit::Count->say($this->opened).' of '.Unit::Count->say($this->fights), $share, Tone::Own),
                new SplitPart('Fights opened another way', 100 - $share, Tone::Lost),
            ]);
        }

        return new Module('Stealth', $blocks, icon: $this->icon);
    }
}
