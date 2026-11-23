<?php

namespace App\Support\ClassPage\Warrior;

use App\Support\ClassPage\Module;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\SpellTotals;
use App\Support\StateKind;

/** The shouts cast, and how much of the fight time Battle Shout was up. */
final readonly class Shouts
{
    public function __construct(private ClassRows $rows) {}

    /** Battle Shout up, as a share of fight time from 0 to 100. */
    public function upShare(): float
    {
        $fights = $this->rows->states()->fights()->ms;
        $up = $this->rows->states()->sum(StateKind::OwnBuff, 'Battle Shout')->fightMs;

        return $fights === 0 ? 0.0 : min(100.0, $up / $fights * 100);
    }

    public function module(): ?Module
    {
        $cast = array_values(array_filter($this->rows->spells()->named(...Spells::SHOUTS), fn (SpellTotals $spell): bool => $spell->casts > 0));
        if ($cast === []) {
            return null;
        }
        $rows = array_map(fn (SpellTotals $spell): RankRow => new RankRow($spell->name, $spell->casts, Spells::icon($spell), Tone::Own), $cast);
        $up = $this->upShare();

        return new Module('Shouts', [new Rank($rows)], note: $up > 0 ? 'Battle Shout was up for '.Unit::Count->say($up).'% of fight time.' : null);
    }
}
