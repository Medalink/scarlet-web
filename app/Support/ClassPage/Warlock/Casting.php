<?php

namespace App\Support\ClassPage\Warlock;

use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Meter;
use App\Support\ClassRows\MeterSpell;
use App\Support\ClassRows\SpellTotals;
use App\Support\ClassRows\Tally;
use App\View\Format;

/** The row of the curses and banes cast, the damage by spell from the client's meter and what finished the kills. */
final readonly class Casting
{
    private const int CURSES = 7;

    private const int DAMAGE = 6;

    public function __construct(private ClassRows $rows) {}

    public function row(): ?Row
    {
        $modules = array_values(array_filter([$this->curses(), $this->damage(), $this->kills()]));

        return $modules === [] ? null : new Row($modules);
    }

    private function curses(): ?Module
    {
        $spells = array_slice($this->rows->spells()->named('Curse of *', 'Bane of *'), 0, self::CURSES);
        if ($spells === []) {
            return null;
        }

        return new Module('Curses and banes cast', [new Rank(array_map(
            fn (SpellTotals $spell): RankRow => new RankRow($spell->name, $spell->casts, Spells::icon($spell), Tone::Own),
            $spells,
        ))]);
    }

    private function damage(): ?Module
    {
        $meters = $this->rows->meters();
        $listed = array_slice($meters->spells(Meter::Damage), 0, self::DAMAGE);
        $shown = array_sum(array_map(fn (MeterSpell $spell): int => $spell->amount, $listed));
        $whole = max($meters->total(Meter::Damage), $shown);
        if ($listed === [] || $whole === 0) {
            return null;
        }
        $rows = array_map(fn (MeterSpell $spell): RankRow => new RankRow($spell->name, $spell->amount / $whole * 100, Icon::spell($spell->iconId)), $listed);
        $rows[] = new RankRow('Every other spell', ($whole - $shown) / $whole * 100);

        return new Module('Damage by spell', [new Rank($rows, Unit::Percent)], note: 'The meter lists the five largest spells of each fight.');
    }

    private function kills(): ?Module
    {
        $own = $after = 0;
        foreach ($this->rows->tallies()->of(Tally::KillBlow) as $row) {
            if ($row->a === 0) {
                $after += $row->n;

                continue;
            }
            $own += $row->n;
        }
        $party = $this->rows->partyKills();
        $all = $own + $after + $party;
        if ($all === 0) {
            return null;
        }

        return new Module('What finished the kill', [new Rank([
            new RankRow('Your killing blow', $own / $all * 100),
            new RankRow('A curse, a bane or the demon, after you looked away', $after / $all * 100),
            new RankRow('A party member', $party / $all * 100),
        ], Unit::Percent, true)], note: $after === 0 ? null : Format::count($after, 'creature').' died to a curse, a bane or the demon with no killing blow of yours.');
    }
}
