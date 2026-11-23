<?php

namespace App\Support\ClassPage\Warlock;

use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Tone;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\FlowDirection;
use App\Support\ClassRows\FlowPlace;
use App\Support\ClassRows\ItemFlow;
use App\Support\ClassRows\SpellTotals;
use App\Support\ClassRows\Tally;
use App\Support\ClassRows\TallyRow;
use App\View\Format;

/** What the warlock does for the party: the stones, the summons and the control spells. */
final readonly class Party
{
    private const string SOULSTONE_RESURRECTION = 'Soulstone Resurrection*';

    private const array CONTROL = ['Fear', 'Death Coil', 'Howl of Terror', 'Banish', 'Enslave Demon', 'Subjugate Demon'];

    private const int CONTROLS = 5;

    private ItemFlow $healthstones;

    public function __construct(private ClassRows $rows, private Spells $spells)
    {
        $this->healthstones = $rows->items('*Healthstone')->named('*Healthstone');
    }

    /** Healthstones that left the bags in a trade. */
    public function handedOut(): int
    {
        return $this->healthstones->sum(FlowDirection::Out, FlowPlace::Trade);
    }

    /** @return list<Row> */
    public function rows(): array
    {
        $modules = array_values(array_filter([$this->stones(), $this->summons(), $this->control()]));

        return $modules === [] ? [] : [new Row($modules)];
    }

    private function stones(): ?Module
    {
        $made = $this->spells->healthstones->casts;
        $soulstones = $this->spells->soulstones->casts;
        if ($made === 0 && $soulstones === 0) {
            return null;
        }
        $healthIcon = $this->icon('*Healthstone');
        $rows = [new RankRow('Healthstones made', $made, Spells::icon($this->spells->healthstones), Tone::Own)];
        if ($this->healthstones->out() > 0) {
            $rows[] = new RankRow('Eaten', $this->healthstones->sum(FlowDirection::Out, FlowPlace::Other), $healthIcon);
            $rows[] = new RankRow('Handed to another player', $this->handedOut(), $healthIcon);
        }
        $rows[] = new RankRow('Soulstones made', $soulstones, Spells::icon($this->spells->soulstones), Tone::Own);
        $rows[] = new RankRow('Deaths undone by a soulstone', $this->soulstoneResurrections(), $this->icon('*Soulstone'));

        return new Module('Stones', [new Rank($rows, of: max(1, ...array_map(fn (RankRow $row): int|float => $row->value, $rows)))]);
    }

    private function summons(): ?Module
    {
        $ritual = $this->spells->ritual;
        if ($ritual->casts === 0 && $ritual->interrupts === 0) {
            return null;
        }

        return new Module('Summons', [new Pair([new PairRow('Players summoned', $ritual->casts), new PairRow('Interrupted', $ritual->interrupts)])], icon: Spells::icon($ritual));
    }

    private function control(): ?Module
    {
        $spells = $this->rows->spells();
        $cast = array_values(array_filter(array_map(fn (string $name): SpellTotals => $spells->sum($name, $name), self::CONTROL), fn (SpellTotals $spell): bool => $spell->casts > 0));
        if ($cast === []) {
            return null;
        }
        usort($cast, fn (SpellTotals $a, SpellTotals $b): int => [$b->casts, $a->name] <=> [$a->casts, $b->name]);
        $cast = array_slice($cast, 0, self::CONTROLS);
        $fear = $this->spells->fear;
        $started = $fear->casts + $fear->interrupts;

        return new Module(
            'Fear and control',
            [new Rank(array_map(fn (SpellTotals $spell): RankRow => new RankRow($spell->name, $spell->casts, Spells::icon($spell), Tone::Own), $cast))],
            note: $fear->interrupts === 0 ? null : Format::percent($fear->interrupts / $started).' of Fear casts were cut short.',
        );
    }

    private function soulstoneResurrections(): int
    {
        $taken = $this->rows->tallies()->of(Tally::ResTaken);
        $names = $this->rows->spellNames(array_values(array_unique(array_map(fn (TallyRow $row): int => $row->b, $taken))));
        $ids = array_keys(array_filter($names, fn (string $name): bool => fnmatch(self::SOULSTONE_RESURRECTION, $name, FNM_CASEFOLD)));

        return $this->rows->tallies()->count(Tally::ResTaken, null, $ids);
    }

    private function icon(string $pattern): ?Icon
    {
        $ids = $this->rows->items($pattern)->idsNamed($pattern);

        return $ids === [] ? null : Icon::item($ids[0]);
    }
}
