<?php

namespace App\Support\ClassPage\Rogue;

use App\Support\ClassPage\Casts;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\FlowDirection;

/** What the rogue does with its hands: pockets picked, locks picked and the lockboxes that left the bags with a Pick Lock. */
final readonly class Hands
{
    public function __construct(private ClassRows $rows, private Spells $spells) {}

    public function pockets(): ?Module
    {
        $spell = $this->spells->pickPocket;
        if ($spell->casts === 0) {
            return null;
        }

        return new Module('Pick Pocket', [new Pair([
            new PairRow('Pockets picked', $spell->casts),
            new PairRow('A played hour', Casts::perHour($spell->casts, $this->rows->playedMs()), Unit::Decimal),
            new PairRow('Most in a row', $spell->streak),
        ])], Span::W5, icon: Casts::icon($spell));
    }

    public function lockboxes(): int
    {
        return $this->rows->items()->named('*Lockbox')->bySpells(FlowDirection::Out, $this->spells->pickLock->ids());
    }

    public function locks(): ?Module
    {
        $spell = $this->spells->pickLock;
        if ($spell->casts === 0) {
            return null;
        }
        $cells = [new PairRow('Locks picked', $spell->casts)];
        if ($this->lockboxes() > 0) {
            $cells[] = new PairRow('Lockboxes opened', $this->lockboxes());
        }
        $cells[] = new PairRow('A played hour', Casts::perHour($spell->casts, $this->rows->playedMs()), Unit::Decimal);

        return new Module('Locks', [new Pair($cells)], icon: Casts::icon($spell));
    }
}
