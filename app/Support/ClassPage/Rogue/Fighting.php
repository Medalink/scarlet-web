<?php

namespace App\Support\ClassPage\Rogue;

use App\Support\ClassPage\Casts;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Meter;
use App\Support\ClassRows\SpellTotals;
use App\Support\ClassRows\Tally;
use App\View\Format;

/** The attacks cast, the escapes, and the Kick: casts from `spell_casts`, interrupts from the client's meter, deaths after a Vanish from `death_after`. */
final readonly class Fighting
{
    private const int DEATH_SECONDS = 10;

    public function __construct(private ClassRows $rows, private Spells $spells) {}

    public function attacks(): ?Module
    {
        $attacks = Casts::cast(Casts::named($this->rows, Spells::ATTACKS));
        $rank = Casts::rank($attacks, 6);
        if ($rank === null) {
            return null;
        }
        $top = $attacks[0];
        $all = array_sum(array_map(fn (SpellTotals $spell): int => $spell->casts, $attacks));

        return new Module('Attacks used', [$rank], Span::W8, $top->name.' was '.Unit::Count->say(Casts::share($top->casts, $all)).'% of these casts.');
    }

    public function escapes(): ?Module
    {
        $rank = Casts::rank(Casts::named($this->rows, Spells::ESCAPES));
        if ($rank === null) {
            return null;
        }
        $deaths = $this->rows->tallies()->count(Tally::DeathAfter, $this->spells->vanish->ids(), range(0, self::DEATH_SECONDS - 1));

        return new Module('Escapes', [$rank], note: $deaths === 0 ? null : Format::count($deaths, 'death').' came within '.self::DEATH_SECONDS.' seconds of a Vanish.');
    }

    public function landed(): int
    {
        return $this->rows->meters()->total(Meter::Interrupts);
    }

    /** Percent of Kicks that landed as an interrupt, by the meter's count. */
    public function landedShare(): float
    {
        return Casts::share($this->landed(), $this->spells->kick->casts);
    }

    public function kick(): ?Module
    {
        $kick = $this->spells->kick;
        if ($kick->casts === 0) {
            return null;
        }

        return new Module('Kick', [new Pair([
            new PairRow('Kicks cast', $kick->casts),
            new PairRow('Interrupts landed', $this->landed()),
            new PairRow('Landed', $this->landedShare(), Unit::Percent),
        ])], icon: Casts::icon($kick), note: 'The meter counts every interrupt, whatever spell made it.');
    }
}
