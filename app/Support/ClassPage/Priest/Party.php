<?php

namespace App\Support\ClassPage\Priest;

use App\Support\ClassPage\Casts;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\FlowDirection;
use App\Support\ClassRows\Meter;
use App\Support\ClassRows\SpellTotals;

/** What the priest does for other players: the dead raised, what it dispelled, the buffs and the control it kept up. */
final readonly class Party
{
    public function __construct(private ClassRows $rows, private Spells $spells) {}

    /** @return list<Row> */
    public function rows(): array
    {
        $modules = array_values(array_filter([$this->raised(), $this->dispels(), $this->fortitude()]));

        return $modules === [] ? [] : [new Row($modules)];
    }

    private function raised(): ?Module
    {
        $spell = $this->spells->resurrection;
        if ($spell->casts === 0) {
            return null;
        }

        return new Module('Raised from the dead', [new Pair([
            new PairRow('Players raised', $spell->casts),
            new PairRow('A played hour', Casts::perHour($spell->casts, $this->rows->playedMs()), Unit::Decimal),
            new PairRow('Most in a row', $spell->streak),
        ])], icon: Casts::icon($spell));
    }

    private function dispels(): ?Module
    {
        $spells = Casts::named($this->rows, Spells::DISPELS);
        $rank = Casts::rank($spells);
        if ($rank === null) {
            return null;
        }
        $casts = array_sum(array_map(fn (SpellTotals $spell): int => $spell->casts, $spells));
        $landed = $this->rows->meters()->total(Meter::Dispels);
        $cells = [new PairRow('Casts', $casts)];
        if ($landed > 0) {
            $cells[] = new PairRow('Dispels landed', $landed);
        }

        return new Module('Dispels', [new Pair($cells), $rank]);
    }

    private function fortitude(): ?Module
    {
        $fortitudes = $this->spells->fortitudes();
        if ($fortitudes === 0) {
            return null;
        }
        $cells = [new PairRow('Power Word: Fortitude', $this->spells->fortitude->casts), new PairRow('Prayer of Fortitude', $this->spells->prayer->casts)];
        $candles = $this->rows->items(Spells::CANDLE)->named(Spells::CANDLE)->bySpells(FlowDirection::Out, $this->spells->prayer->ids());
        if ($candles > 0) {
            $cells[] = new PairRow('Holy Candles used', $candles);
        }
        $top = $this->spells->fortitude->casts >= $this->spells->prayer->casts ? $this->spells->fortitude : $this->spells->prayer;

        return new Module('Fortitude', [new Pair($cells)], icon: Casts::icon($top));
    }
}
