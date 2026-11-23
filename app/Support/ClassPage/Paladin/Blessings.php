<?php

namespace App\Support\ClassPage\Paladin;

use App\Support\ClassPage\Module;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\SpellTotals;

/** The blessings cast, by casts, and the Symbols of Kings spent on the greater ones. */
final readonly class Blessings
{
    private const int SHOWN = 9;

    /** @var list<SpellTotals> the blessings cast, the most cast first */
    private array $cast;

    public function __construct(private ClassRows $rows, private Spells $spells)
    {
        $this->cast = array_values(array_filter($rows->spells()->named(...Spells::BLESSINGS, ...Spells::GREATER), fn (SpellTotals $spell): bool => $spell->casts > 0));
    }

    public function top(): ?SpellTotals
    {
        return $this->cast[0] ?? null;
    }

    public function total(): int
    {
        return $this->spells->blessingCasts();
    }

    /** The Symbols of Kings that left the bags. */
    public function symbols(): int
    {
        return $this->rows->items()->named('Symbol of Kings')->out();
    }

    public function module(): ?Module
    {
        if ($this->cast === []) {
            return null;
        }
        $rows = array_map(fn (SpellTotals $spell): RankRow => new RankRow($spell->name, $spell->casts, Spells::icon($spell), Tone::Own), array_slice($this->cast, 0, self::SHOWN));
        $symbols = $this->symbols();

        return new Module('Blessings', [new Rank($rows)], Span::W5, $symbols > 0 ? Unit::Count->say($symbols).' Symbols of Kings left the bags. Who was blessed is not recorded.' : 'Who was blessed is not recorded.', Spells::icon($this->cast[0]));
    }
}
