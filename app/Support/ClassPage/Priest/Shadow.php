<?php

namespace App\Support\ClassPage\Priest;

use App\Support\ClassPage\Casts;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\StateTotals;
use App\Support\StateKind;

/** The shadow side and the wand: the `a` rows of `state_times` for Shadowform, the `r` rows for Shoot, and the shadow spells cast. */
final readonly class Shadow
{
    private StateTotals $form;

    private StateTotals $wand;

    private int $formRef;

    private int $wandRef;

    public function __construct(private ClassRows $rows)
    {
        $states = $rows->states();
        $this->form = $states->sum(StateKind::OwnBuff, Spells::SHADOWFORM);
        $this->wand = $states->sum(StateKind::AutoRepeat, Spells::WAND);
        $this->formRef = $states->of(StateKind::OwnBuff, Spells::SHADOWFORM)[0]->refId ?? 0;
        $this->wandRef = $states->of(StateKind::AutoRepeat, Spells::WAND)[0]->refId ?? 0;
    }

    public function formHours(): float
    {
        return $this->form->hours();
    }

    public function formShare(): float
    {
        return Casts::share($this->form->ms, $this->rows->playedMs());
    }

    public function formIcon(): ?Icon
    {
        return $this->formRef === 0 ? null : Icon::spell($this->formRef);
    }

    public function wandHours(): float
    {
        return $this->wand->hours();
    }

    public function wandIcon(): ?Icon
    {
        return $this->wandRef === 0 ? null : Icon::spell($this->wandRef);
    }

    /** Percent of the time in fights that the wand was firing. */
    public function wandShare(): float
    {
        return Casts::share($this->wand->fightMs, $this->rows->states()->fights()->ms);
    }

    public function side(): ?Module
    {
        $rank = Casts::rank(Casts::named($this->rows, Spells::SHADOW));
        if ($rank === null) {
            return null;
        }
        $note = $this->form->ms === 0 ? null : 'Shadowform was up for '.Unit::Hours->say($this->formHours()).' hours, '.Unit::Count->say($this->formShare()).'% of played time.';

        return new Module('The shadow side', [$rank], Span::W8, $note);
    }

    public function wand(): ?Module
    {
        if ($this->wand->ms === 0) {
            return null;
        }

        return new Module('Wand', [new Pair([
            new PairRow('Hours of wand', $this->wandHours(), Unit::Hours),
            new PairRow('Share of fight time', $this->wandShare(), Unit::Percent),
        ])], icon: $this->wandIcon());
    }
}
