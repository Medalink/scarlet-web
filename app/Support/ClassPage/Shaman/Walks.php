<?php

namespace App\Support\ClassPage\Shaman;

use App\Support\ClassPage\Casts;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Split;
use App\Support\ClassPage\SplitPart;
use App\Support\ClassPage\Titled;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\FlowDirection;
use App\Support\ClassRows\FlowPlace;
use App\Support\ClassRows\StateTotals;
use App\Support\StateKind;
use App\View\Format;

/** How the shaman came back and got around: Reincarnation and the Ankhs it used, the Ghost Wolf form against the mount, and Astral Recall. */
final readonly class Walks
{
    private StateTotals $wolf;

    private int $wolfRef;

    public function __construct(private ClassRows $rows, private Spells $spells)
    {
        $states = $rows->states(StateKind::OwnBuff);
        $this->wolf = $states->sum(StateKind::OwnBuff, Spells::WOLF);
        $this->wolfRef = $states->of(StateKind::OwnBuff, Spells::WOLF)[0]->refId ?? 0;
    }

    public function wolfHours(): float
    {
        return $this->wolf->hours();
    }

    public function wolfShare(): float
    {
        return Casts::share($this->wolf->ms, $this->rows->playedMs());
    }

    public function wolfIcon(): ?Icon
    {
        return $this->wolfRef === 0 ? null : Icon::spell($this->wolfRef);
    }

    public function walkedOff(): float
    {
        return Casts::share($this->spells->reincarnation->casts, $this->rows->deaths());
    }

    public function back(): ?Module
    {
        $spell = $this->spells->reincarnation;
        $deaths = $this->rows->deaths();
        if ($spell->casts === 0 && $deaths === 0) {
            return null;
        }
        $cells = [new PairRow('Reincarnations', $spell->casts)];
        $ankhs = $this->rows->items(Spells::ANKH)->named(Spells::ANKH)->sum(FlowDirection::In, FlowPlace::Vendor);
        if ($ankhs > 0) {
            $cells[] = new PairRow('Ankhs bought', $ankhs);
        }
        $blocks = [new Pair($deaths > 0 ? [...$cells, new PairRow('Deaths walked off', $this->walkedOff(), Unit::Percent)] : $cells)];
        if ($deaths > 0) {
            $walked = min($spell->casts, $deaths);
            $blocks[] = new Split(array_values(array_filter([
                $walked > 0 ? new SplitPart('Walked off, '.Unit::Count->say($walked).' of '.Format::count($deaths, 'death'), $walked, Tone::Own) : null,
                $deaths > $walked ? new SplitPart('Released', $deaths - $walked, Tone::Lost) : null,
            ])));
        }

        return new Module('Back from the dead', $blocks, icon: Casts::icon($spell));
    }

    public function travel(): ?Module
    {
        $recalls = $this->spells->recall->casts;
        $mounted = $this->rows->states(StateKind::Mount)->sum(StateKind::Mount)->hours();
        if ($this->wolf->ms === 0 && $mounted === 0.0 && $recalls === 0) {
            return null;
        }
        $wolf = $this->wolfHours();
        $blocks = [new Pair([
            new PairRow('Hours as a wolf', $wolf, Unit::Hours),
            new PairRow('Of travel time', Casts::share($wolf, $wolf + $mounted), Unit::Percent),
            new PairRow('Astral Recalls', $recalls),
        ])];
        if ($wolf > 0 || $mounted > 0) {
            $blocks[] = new Titled('Hours travelling', [new Rank([
                new RankRow(Spells::WOLF, $wolf, $this->wolfIcon(), Tone::Own),
                new RankRow('Mounted', $mounted, Icon::item(Spells::MOUNT_ITEM), Tone::Lost),
            ], Unit::Hours, of: max($wolf, $mounted))]);
        }

        return new Module('Ghost Wolf and Astral Recall', $blocks, icon: $this->wolfIcon());
    }
}
