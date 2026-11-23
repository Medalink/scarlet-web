<?php

namespace App\Support\ClassPage\Paladin;

use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Split;
use App\Support\ClassPage\SplitPart;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Meter;
use App\Support\ClassRows\SpellTotals;
use App\Support\ClassRows\Tally;
use App\Support\ClassRows\TallyRow;
use App\Support\StateKind;

/** What the paladin cast in a fight: the spells for the undead, the bubble, Lay on Hands, the heals and the control. */
final readonly class Combat
{
    private const int DEATH_SECONDS = 10;

    public function __construct(private ClassRows $rows, private Spells $spells) {}

    public function undead(): ?Module
    {
        $cast = $this->cast(Spells::UNDEAD);
        if ($cast === []) {
            return null;
        }

        return new Module('Spells for the undead', [new Rank($this->ranked($cast))], Span::W8, 'Casts. A kill is not recorded with the type of the creature, so the page cannot say how many were undead.');
    }

    /** The shields and protections cast. */
    public function bubbles(): int
    {
        return $this->spells->divineShield->casts + $this->spells->divineProtection->casts;
    }

    /** Hearthstones cast while a bubble was up. */
    public function hearths(): int
    {
        $under = array_values(array_filter($this->rows->tallies()->of(Tally::CastUnder), fn (TallyRow $row): bool => in_array($row->a, $this->bubbleIds(), true)));
        $names = $this->rows->spellNames(array_values(array_unique(array_map(fn (TallyRow $row): int => $row->b, $under))));

        return array_sum(array_map(fn (TallyRow $row): int => ($names[$row->b] ?? '') === 'Hearthstone' ? $row->n : 0, $under));
    }

    public function bubble(): ?Module
    {
        $bubbles = $this->bubbles();
        if ($bubbles === 0) {
            return null;
        }
        $died = $this->bubbleDeaths();
        $lived = max(0, 100 - $died / $bubbles * 100);

        return new Module('The bubble', [
            new Pair([
                new PairRow('Divine Shields', $this->spells->divineShield->casts),
                new PairRow('Divine Protections', $this->spells->divineProtection->casts),
                new PairRow('Hearthstones used inside one', $this->hearths()),
            ]),
            new Split([new SplitPart('Lived', $lived, Tone::Own), new SplitPart($died.' died within '.self::DEATH_SECONDS.' seconds of one ending', 100 - $lived, Tone::Lost)]),
        ], icon: Spells::icon($this->spells->divineShield));
    }

    public function layOnHands(): ?Module
    {
        $stand = $this->spells->layOnHands;
        if ($stand->casts === 0) {
            return null;
        }
        $deaths = $stand->ids() === [] ? 0 : $this->rows->tallies()->count(Tally::DeathAfter, $stand->ids(), range(0, self::DEATH_SECONDS - 1));

        return new Module('Lay on Hands', [
            new Pair([
                new PairRow('Casts', $stand->casts),
                new PairRow('Used in a fight', $stand->fightCasts / $stand->casts * 100, Unit::Percent),
                new PairRow('Deaths within '.self::DEATH_SECONDS.' seconds of one', $deaths),
            ]),
        ], icon: Spells::icon($stand));
    }

    public function heals(): ?Module
    {
        $cast = $this->cast(Spells::HEALS);
        if ($cast === []) {
            return null;
        }
        $whole = $this->rows->meters()->total(Meter::Healing);
        $listed = [];
        foreach ($this->rows->meters()->spells(Meter::Healing) as $spell) {
            if ($whole > 0 && in_array($spell->name, Spells::HEALS, true)) {
                $listed[] = $spell->name.' '.Unit::Count->say(min(100, $spell->amount / $whole * 100)).'%';
            }
        }

        return new Module('Heals', [new Rank($this->ranked($cast))], note: $listed === [] ? null : 'The healing meter gave '.implode(', ', $listed).' of the healing.');
    }

    public function control(): ?Module
    {
        $cast = $this->cast(Spells::CONTROL);
        $fury = $this->rows->states(StateKind::OwnBuff)->sum(StateKind::OwnBuff, 'Righteous Fury');
        if ($cast === [] && $fury->ms === 0) {
            return null;
        }
        $blocks = [];
        if ($cast !== []) {
            $blocks[] = new Rank($this->ranked($cast));
        }
        if ($fury->ms > 0) {
            $blocks[] = new Pair([new PairRow('Righteous Fury', $fury->hours(), Unit::Hours, 'hours with it up')]);
        }

        return new Module('Hammer of Justice and control', $blocks);
    }

    private function bubbleDeaths(): int
    {
        $ids = $this->bubbleIds();

        return $ids === [] ? 0 : $this->rows->tallies()->count(Tally::DeathAfterAura, $ids, range(0, self::DEATH_SECONDS - 1));
    }

    /** @return list<int> */
    private function bubbleIds(): array
    {
        return [...$this->spells->divineShield->ids(), ...$this->spells->divineProtection->ids()];
    }

    /**
     * @param  list<string>  $names
     * @return list<SpellTotals>
     */
    private function cast(array $names): array
    {
        return array_values(array_filter($this->rows->spells()->named(...$names), fn (SpellTotals $spell): bool => $spell->casts > 0));
    }

    /**
     * @param  list<SpellTotals>  $spells
     * @return list<RankRow>
     */
    private function ranked(array $spells): array
    {
        return array_map(fn (SpellTotals $spell): RankRow => new RankRow($spell->name, $spell->casts, Spells::icon($spell), Tone::Own), $spells);
    }
}
