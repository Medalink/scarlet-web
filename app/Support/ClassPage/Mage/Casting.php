<?php

namespace App\Support\ClassPage\Mage;

use App\Support\ClassPage\Hue;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\SpellTotals;
use App\Support\ClassRows\Tally;

/** The attack spells by casts, coloured by school, beside the big pull: the most kills in one fight, the fights with five or more and the hours of Blizzard. */
final readonly class Casting
{
    public const int BIG = 5;

    private const int SPELLS = 9;

    public function __construct(private ClassRows $rows, private Spells $spells) {}

    /** The most kills of your own in one fight, 0 when no fight had one. */
    public function mostKills(): int
    {
        $most = 0;
        foreach ($this->rows->tallies()->of(Tally::FightKills) as $row) {
            $most = max($most, $row->a);
        }

        return $most;
    }

    /** Fights with at least `BIG` kills of your own. */
    public function bigPulls(): int
    {
        $fights = 0;
        foreach ($this->rows->tallies()->of(Tally::FightKills) as $row) {
            if ($row->a >= self::BIG) {
                $fights += $row->n;
            }
        }

        return $fights;
    }

    public function row(): ?Row
    {
        $pull = $this->pull();
        $modules = array_values(array_filter([$this->casts($pull === null ? Span::W12 : Span::W8), $pull]));

        return $modules === [] ? null : new Row($modules);
    }

    private function casts(Span $span): ?Module
    {
        $attacks = array_slice(array_values(array_filter($this->rows->spells()->named(...Spells::ATTACKS), fn (SpellTotals $spell): bool => $spell->casts > 0)), 0, self::SPELLS);
        if ($attacks === []) {
            return null;
        }

        return new Module('Spells cast', [new Rank(array_map(
            fn (SpellTotals $spell): RankRow => new RankRow($spell->name, $spell->casts, Spells::icon($spell), self::school($spell->name)),
            $attacks,
        ))], $span);
    }

    private function pull(): ?Module
    {
        $most = $this->mostKills();
        if ($most === 0) {
            return null;
        }
        $blizzard = $this->spells->blizzard;

        return new Module('The big pull', [new Pair([
            new PairRow('Most kills in one fight', $most),
            new PairRow('Fights with '.self::BIG.' kills or more', $this->bigPulls()),
            new PairRow('Hours of Blizzard', $blizzard->channelMs / 3_600_000, Unit::Decimal),
        ])], icon: Spells::icon($blizzard));
    }

    private static function school(string $name): Tone|Hue
    {
        return match (true) {
            in_array($name, Spells::FIRE, true) => new Hue(Schools::FIRE_HUE),
            in_array($name, Spells::ARCANE, true) => new Hue(Schools::ARCANE_HUE),
            default => Tone::Own,
        };
    }
}
