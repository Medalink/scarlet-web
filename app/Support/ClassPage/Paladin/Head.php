<?php

namespace App\Support\ClassPage\Paladin;

use App\Support\ClassPage\Callout;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\StateKind;

/** The callouts of the head: the warhorse, the blessing cast most and the bubble. */
final readonly class Head
{
    public function __construct(private ClassRows $rows, private Spells $spells, private Blessings $blessings, private Combat $combat) {}

    /** @return list<Callout> */
    public function callouts(): array
    {
        $callouts = [];
        $mount = $this->mount();
        if ($mount !== null) {
            $callouts[] = $mount;
        }
        $top = $this->blessings->top();
        if ($top !== null) {
            $callouts[] = new Callout('podium', $top->name, 'The blessing cast most', Unit::Count->say($top->casts / $this->blessings->total() * 100).'% of blessings', true);
        }
        $bubbles = $this->combat->bubbles();
        if ($bubbles > 0) {
            $callouts[] = new Callout('shieldrack', 'Divine Shield', Unit::Count->say($bubbles).' '.($bubbles === 1 ? 'bubble' : 'bubbles'), Unit::Count->say($this->combat->hearths()).' of them left with a Hearthstone');
        }

        return $callouts;
    }

    private function mount(): ?Callout
    {
        $rides = $this->rows->states(StateKind::Mount)->of(StateKind::Mount, '*Warhorse', '*Charger');
        $summons = $this->spells->mount->casts;
        $ms = array_sum(array_map(fn ($state): int => $state->ms, $rides));
        if ($ms === 0 && $summons === 0) {
            return null;
        }
        $name = $rides === [] ? 'Warhorse' : trim(str_replace('Summon', '', $rides[0]->name));
        $summoned = 'Summoned '.Unit::Count->say($summons).' '.($summons === 1 ? 'time' : 'times');

        return $ms === 0 ? new Callout('warhorse', $name, $summoned) : new Callout('warhorse', $name, 'Ridden for '.Unit::Hours->say($ms / 3_600_000).' hours', $summoned);
    }
}
