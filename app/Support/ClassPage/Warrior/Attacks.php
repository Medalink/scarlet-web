<?php

namespace App\Support\ClassPage\Warrior;

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
use App\Support\ClassRows\SpellTotals;
use App\Support\ClassRows\Tally;

/** The attacks used, by casts, and Execute: how many killing blows came within 3 seconds of one. */
final readonly class Attacks
{
    private const int SHOWN = 9;

    /** @var list<SpellTotals> the attacks cast, the most cast first */
    private array $cast;

    public function __construct(private ClassRows $rows, private Spells $spells)
    {
        $this->cast = array_values(array_filter($rows->spells()->named(...Spells::ATTACKS), fn (SpellTotals $spell): bool => $spell->casts > 0));
    }

    public function total(): int
    {
        return array_sum(array_map(fn (SpellTotals $spell): int => $spell->casts, $this->cast));
    }

    public function top(): ?SpellTotals
    {
        return $this->cast[0] ?? null;
    }

    /** @return list<Module|null> the attacks and Execute, side by side */
    public function modules(): array
    {
        return [$this->rank(), $this->execute()];
    }

    /** Killing blows that came within 3 seconds of an Execute, and all killing blows. */
    public function executeKills(): int
    {
        $ids = $this->spells->execute->ids();

        return $ids === [] ? 0 : $this->rows->tallies()->count(Tally::KillBlow, $ids);
    }

    public function kills(): int
    {
        return $this->rows->tallies()->count(Tally::KillBlow);
    }

    private function rank(): ?Module
    {
        if ($this->cast === []) {
            return null;
        }
        $rows = array_map(fn (SpellTotals $spell): RankRow => new RankRow($spell->name, $spell->casts, Spells::icon($spell), Tone::Own), array_slice($this->cast, 0, self::SHOWN));

        return new Module('Attacks used', [new Rank($rows)], Span::W8);
    }

    private function execute(): ?Module
    {
        $execute = $this->spells->execute;
        if ($execute->casts === 0) {
            return null;
        }
        $killed = $this->executeKills();
        $kills = $this->kills();
        $share = $kills === 0 ? 0 : min(100, $killed / $kills * 100);

        return new Module('Execute', [
            new Pair([new PairRow('Casts', $execute->casts), new PairRow('Kills within 3 seconds of one', $killed), new PairRow('Share of kills finished by one', $share, Unit::Percent)]),
            new Split([new SplitPart('Kills finished by an Execute', $share, Tone::Own), new SplitPart('Other kills', 100 - $share, Tone::Lost)]),
        ], icon: Spells::icon($execute));
    }
}
