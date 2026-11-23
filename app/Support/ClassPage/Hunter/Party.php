<?php

namespace App\Support\ClassPage\Hunter;

use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\StateKind;

/** What the hunter does for the party: Trueshot Aura and Aspect of the Pack, Freezing Trap, Tranquilizing Shot and Mend Pet. */
final readonly class Party
{
    public function __construct(private ClassRows $rows, private Spells $spells) {}

    /** @return list<Row> */
    public function rows(): array
    {
        $modules = array_values(array_filter([$this->auras(), $this->freezing(), $this->mending()]));

        return $modules === [] ? [] : [new Row($modules)];
    }

    private function auras(): ?Module
    {
        $trueshot = $this->rows->states()->sum(StateKind::OwnBuff, 'Trueshot Aura');
        $pack = $this->rows->states()->sum(StateKind::OwnBuff, 'Aspect of the Pack');
        if ($trueshot->ms === 0 && $pack->ms === 0) {
            return null;
        }

        return new Module('Auras for the group', [
            new Pair([
                new PairRow('Trueshot Aura', $trueshot->hours(), Unit::Hours, 'hours up'),
                new PairRow('Aspect of the Pack', $pack->hours(), Unit::Hours, 'hours up'),
                new PairRow('Packs cast', $this->spells->pack->casts),
            ]),
        ], note: 'The rows hold no group time, so these are hours up, grouped or not.');
    }

    private function freezing(): ?Module
    {
        $trap = $this->spells->freezing;
        if ($trap->casts === 0) {
            return null;
        }
        $played = $this->rows->playedMs();

        return new Module('Freezing Trap', [
            new Pair([
                new PairRow('Casts', $trap->casts),
                new PairRow('Casts a played hour', $played === 0 ? 0 : $trap->casts / ($played / 3_600_000), Unit::Decimal),
                new PairRow('Set in a fight', $trap->fightCasts / $trap->casts * 100, Unit::Percent),
            ]),
        ], icon: Spells::icon($trap), note: 'Casts in every place; the rows do not say where a spell was cast.');
    }

    private function mending(): ?Module
    {
        $tranq = $this->spells->tranquilizing;
        $mend = $this->spells->mend;
        if ($tranq->casts + $mend->casts === 0) {
            return null;
        }

        return new Module('Tranquilizing Shot and Mend Pet', [
            new Pair([
                new PairRow('Tranquilizing Shots', $tranq->casts),
                new PairRow('Mend Pets', $mend->casts),
                new PairRow('Mend Pet channelled', $mend->channelMs / 3_600_000, Unit::Hours, 'hours'),
            ]),
        ]);
    }
}
