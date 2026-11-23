<?php

namespace App\Support\ClassPage\Hunter;

use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Split;
use App\Support\ClassPage\SplitPart;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\SpellTotals;
use App\Support\ClassRows\Tally;
use App\Support\StateKind;

/** The traps set, Feign Death and the minimap tracking. */
final readonly class Field
{
    private const int FEIGN_SECONDS = 5;

    public function __construct(private ClassRows $rows, private Spells $spells) {}

    /** Feign Deaths not followed by a death within 5 seconds, as a share of all, 0 to 100. */
    public function feignWorked(): float
    {
        $feign = $this->spells->feign;

        return $feign->casts === 0 ? 0.0 : max(0.0, 100 - $this->feignDeaths() / $feign->casts * 100);
    }

    public function traps(): ?Module
    {
        $cast = array_values(array_filter($this->rows->spells()->named(...Spells::TRAPS), fn (SpellTotals $spell): bool => $spell->casts > 0));
        if ($cast === []) {
            return null;
        }

        return new Module('Traps', [new Rank(array_map(fn (SpellTotals $spell): RankRow => new RankRow($spell->name, $spell->casts, Spells::icon($spell), Tone::Own), $cast))]);
    }

    public function feign(): ?Module
    {
        $feign = $this->spells->feign;
        if ($feign->casts === 0) {
            return null;
        }
        $died = $this->feignDeaths();
        $lived = $this->feignWorked();

        return new Module('Feign Death', [
            new Pair([
                new PairRow('Casts', $feign->casts),
                new PairRow('In combat', $feign->fightCasts / $feign->casts * 100, Unit::Percent),
                new PairRow('Deaths within '.self::FEIGN_SECONDS.' seconds of one', $died),
            ]),
            new Split([new SplitPart('Lived', $lived, Tone::Own), new SplitPart('Died within '.self::FEIGN_SECONDS.' seconds', 100 - $lived, Tone::Lost)]),
        ], icon: Spells::icon($feign));
    }

    public function tracking(): ?Module
    {
        $states = array_values(array_filter($this->rows->states(StateKind::Tracking)->of(StateKind::Tracking), fn ($state): bool => $state->refId > 0));
        if ($states === []) {
            return null;
        }
        $names = $this->rows->spellNames(array_map(fn ($state): int => $state->refId, $states));
        $hours = [];
        foreach ($states as $state) {
            $label = $names[$state->refId] ?? $state->name;
            $hours[$label] = ($hours[$label] ?? 0) + $state->hours();
        }
        arsort($hours);

        return new Module('Tracking', [new Rank(array_map(fn (string $label, float $value): RankRow => new RankRow($label, $value, null, Tone::Own), array_keys($hours), array_values($hours)), Unit::Hours)]);
    }

    private function feignDeaths(): int
    {
        $ids = $this->spells->feign->ids();

        return $ids === [] ? 0 : $this->rows->tallies()->count(Tally::DeathAfter, $ids, range(0, self::FEIGN_SECONDS - 1));
    }
}
