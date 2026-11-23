<?php

namespace App\Support\ClassPage\Mage;

use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\Drinking;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Titled;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Meter;
use App\Support\ClassRows\SpellTotals;
use App\View\Format;

/** The row of Polymorph, the escapes and mana. */
final readonly class Control
{
    public function __construct(private ClassRows $rows, private Spells $spells, private Conjuring $conjuring) {}

    public function row(ClassFacts $facts): ?Row
    {
        $modules = array_values(array_filter([$this->polymorph(), $this->escapes(), $this->mana($facts)]));

        return $modules === [] ? null : new Row($modules);
    }

    private function polymorph(): ?Module
    {
        $sheep = $this->spells->polymorph;
        if ($sheep->casts === 0 && $sheep->interrupts === 0) {
            return null;
        }
        $kinds = array_values(array_filter($this->rows->spells()->named('Polymorph', 'Polymorph: *'), fn (SpellTotals $spell): bool => $spell->casts > 0));

        $blocks = [new Pair([
            new PairRow('Casts', $sheep->casts),
            new PairRow('In a fight', $sheep->casts === 0 ? 0 : $sheep->fightCasts / $sheep->casts * 100, Unit::Percent),
            new PairRow('Interrupted', $sheep->interrupts),
        ])];
        if ($kinds !== []) {
            $blocks[] = new Rank(array_map(fn (SpellTotals $spell): RankRow => new RankRow($spell->name, $spell->casts, Spells::icon($spell), Tone::Own), $kinds));
        }

        return new Module('Polymorph', $blocks, icon: Spells::icon($sheep));
    }

    private function escapes(): ?Module
    {
        $spells = array_values(array_filter(
            [$this->spells->frostNova, $this->spells->manaShield, $this->spells->blink, $this->spells->counterspell, $this->spells->iceBlock],
            fn (SpellTotals $spell): bool => $spell->casts > 0,
        ));
        if ($spells === []) {
            return null;
        }
        usort($spells, fn (SpellTotals $a, SpellTotals $b): int => [$b->casts, $a->name] <=> [$a->casts, $b->name]);
        $landed = $this->rows->meters()->total(Meter::Interrupts);
        $counter = $this->spells->counterspell;

        return new Module(
            'Escapes',
            [new Rank(array_map(fn (SpellTotals $spell): RankRow => new RankRow($spell->name, $spell->casts, Spells::icon($spell), Tone::Own), $spells))],
            note: $counter->casts > 0 && $landed > 0 ? Format::count($landed, 'interrupt').' landed in '.Format::count($counter->casts, 'Counterspell cast').'.' : null,
        );
    }

    private function mana(ClassFacts $facts): ?Module
    {
        $evocation = $this->spells->evocation;
        $gems = $this->spells->conjureGem->casts;
        $drinks = $this->drinking($facts);
        $used = $this->conjuring->gemsUsed();
        if ($evocation->casts === 0 && $gems === 0 && $used === 0 && $drinks === null) {
            return null;
        }
        $blocks = [new Pair([new PairRow('Evocation channels', $evocation->casts), new PairRow('Mana gems conjured', $gems), new PairRow('Mana gems used', $used)])];
        if ($drinks !== null) {
            $blocks[] = $drinks;
        }

        return new Module('Mana', $blocks, icon: Spells::icon($evocation));
    }

    private function drinking(ClassFacts $facts): ?Titled
    {
        $mages = $facts->peers->mean(Drinking::FIGURE);
        $every = $facts->peers->mean(Drinking::FIGURE, true);
        if ($mages === null || $every === null) {
            return null;
        }

        return new Titled('Time spent drinking, of played time', [new Rank([
            new RankRow(Format::characterName($facts->name), Drinking::share($this->rows), tone: Tone::Own),
            new RankRow('Mages', $mages),
            new RankRow('Every class', $every),
        ], Unit::PercentTenth, true)], Drinking::icon($this->rows));
    }
}
