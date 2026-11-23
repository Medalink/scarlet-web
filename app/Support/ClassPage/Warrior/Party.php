<?php

namespace App\Support\ClassPage\Warrior;

use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Split;
use App\Support\ClassPage\SplitPart;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Meter;
use App\Support\ClassRows\SpellTotals;

/** What the warrior does for the party: holding the pull, interrupts and Battle Shout. */
final readonly class Party
{
    public function __construct(private ClassRows $rows, private Spells $spells, private Shouts $shouts) {}

    /** @return list<Row> */
    public function rows(): array
    {
        $modules = array_values(array_filter([$this->holding(), $this->interrupts(), $this->battleShout()]));

        return $modules === [] ? [] : [new Row($modules)];
    }

    private function holding(): ?Module
    {
        $cast = $this->cast(Spells::HOLDING);
        if ($cast === []) {
            return null;
        }

        return new Module('Holding the pull', [new Rank($this->ranked($cast))], note: 'Casts in every place; the rows do not say where a spell was cast.');
    }

    private function interrupts(): ?Module
    {
        $cast = $this->cast(Spells::INTERRUPTS);
        $landed = $this->rows->meters()->total(Meter::Interrupts);
        if ($cast === [] && $landed === 0) {
            return null;
        }
        $casts = array_sum(array_map(fn (SpellTotals $spell): int => $spell->casts, $cast));
        $blocks = [];
        if ($cast !== []) {
            $blocks[] = new Rank($this->ranked($cast));
        }
        $blocks[] = new Pair([
            new PairRow('Interrupts landed', $landed),
            new PairRow('Landed of casts', $casts === 0 ? 0 : min(100, $landed / $casts * 100), Unit::Percent),
        ]);

        return new Module('Interrupts', $blocks, note: 'Landed is the client\'s interrupt meter, which counts every interrupt of yours.');
    }

    private function battleShout(): ?Module
    {
        $shout = $this->spells->battleShout;
        if ($shout->casts === 0) {
            return null;
        }
        $played = $this->rows->playedMs();
        $up = $this->shouts->upShare();

        return new Module('Battle Shout', [
            new Pair([
                new PairRow('Casts', $shout->casts),
                new PairRow('Casts a played hour', $played === 0 ? 0 : $shout->casts / ($played / 3_600_000), Unit::Decimal),
                new PairRow('Up in fights', $up, Unit::Percent),
            ]),
            new Split([new SplitPart('Fight time it was up', $up, Tone::Own), new SplitPart('Fight time without', 100 - $up, Tone::Lost)]),
        ], icon: Spells::icon($shout));
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
