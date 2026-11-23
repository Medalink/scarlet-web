<?php

namespace App\Support\ClassPage\Mage;

use App\Support\ClassPage\Block;
use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Split;
use App\Support\ClassPage\SplitPart;
use App\Support\ClassPage\TalentModule;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Meter;
use App\Support\ClassRows\SpellTotals;
use App\Support\ClassRows\StateTotals;
use App\Support\ClassRows\Tally;
use App\Support\StateKind;

/** The modules of the mage talents the latest build holds: Ice Barrier, Cold Snap, Ice Block, Presence of Mind, Arcane Power, Pyroblast and Combustion. */
final readonly class Talents
{
    private const int DEATH_SECONDS = 10;

    private const int SPENT_ON = 3;

    public function __construct(private ClassRows $rows, private Spells $spells) {}

    /** @return list<TalentModule> one for each held talent that has a module, in the build's order */
    public function of(ClassFacts $facts): array
    {
        $modules = [];
        foreach ($facts->talents as $held) {
            $blocks = match ($held->name) {
                'Ice Barrier' => $this->barrier(),
                'Cold Snap' => $this->snap(),
                'Ice Block' => $this->block(),
                'Presence of Mind' => $this->presence(),
                'Arcane Power' => $this->power(),
                'Pyroblast' => $this->pyroblast(),
                'Combustion' => $this->combustion(),
                default => null,
            };
            if ($blocks === null) {
                continue;
            }
            $modules[] = new TalentModule($held->name, $held->points, $held->max, $blocks['blocks'], $held->spellId === 0 ? null : Icon::spell($held->spellId), $blocks['note']);
        }

        return $modules;
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function barrier(): array
    {
        $barrier = $this->spells->iceBarrier;
        $absorbed = 0;
        foreach ($this->rows->meters()->spells(Meter::Absorbs) as $spell) {
            if ($spell->name === $barrier->name) {
                $absorbed = $spell->amount;
            }
        }
        $cells = [new PairRow('Casts', $barrier->casts)];
        if ($absorbed > 0) {
            $cells[] = new PairRow('Damage absorbed', $absorbed, Unit::Short);
        }
        $cells[] = new PairRow('In a fight', self::share($barrier->fightCasts, $barrier->casts), Unit::Percent);

        return [
            'blocks' => [new Pair($cells), self::split(self::share($barrier->fightCasts, $barrier->casts), 'Cast in a fight', 'Cast out of a fight')],
            'note' => $absorbed > 0 ? 'The meter lists the five largest spells of each fight, so the damage absorbed counts the fights where it listed Ice Barrier.' : null,
        ];
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function snap(): array
    {
        return $this->cast($this->spells->coldSnap);
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function combustion(): array
    {
        return $this->cast($this->spells->combustion);
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function block(): array
    {
        $block = $this->spells->iceBlock;
        $auras = array_map(fn (StateTotals $state): int => $state->refId, $this->rows->states(StateKind::OwnBuff)->of(StateKind::OwnBuff, $block->name));
        $deaths = $auras === [] ? 0 : $this->rows->tallies()->count(Tally::DeathAfterAura, $auras, range(0, self::DEATH_SECONDS - 1));
        $lived = max(0, $block->casts - $deaths);

        return [
            'blocks' => [
                new Pair([
                    new PairRow('Casts', $block->casts),
                    new PairRow('In a fight', $block->fightCasts),
                    new PairRow('Deaths within '.self::DEATH_SECONDS.' s of one ending', $deaths),
                ]),
                self::split(self::share($lived, $block->casts), 'Lived', 'Died within '.self::DEATH_SECONDS.' seconds of one ending'),
            ],
            'note' => null,
        ];
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function presence(): array
    {
        $presence = $this->spells->presence;
        $under = $this->under($presence);
        $used = array_sum($under);

        return [
            'blocks' => self::with(
                new Pair([new PairRow('Casts', $presence->casts), new PairRow('Spent on a spell', $used), new PairRow('Used', self::share($used, $presence->casts), Unit::Percent)]),
                $this->spentOn($under),
            ),
            'note' => null,
        ];
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function power(): array
    {
        $power = $this->spells->arcanePower;
        $under = $this->under($power);
        $used = array_sum($under);

        return [
            'blocks' => self::with(
                new Pair([new PairRow('Casts', $power->casts), new PairRow('Spells cast under it', $used), new PairRow('Spells a cast', $power->casts === 0 ? 0 : $used / $power->casts, Unit::Decimal)]),
                $this->spentOn($under),
            ),
            'note' => null,
        ];
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function pyroblast(): array
    {
        $pyroblast = $this->spells->pyroblast;
        $auras = $this->auras($this->spells->presence);
        $instant = $auras === [] ? 0 : $this->rows->tallies()->count(Tally::CastUnder, $auras, $pyroblast->ids());
        $share = self::share(min($instant, $pyroblast->casts), $pyroblast->casts);

        return [
            'blocks' => [
                new Pair([new PairRow('Casts', $pyroblast->casts), new PairRow('Under Presence of Mind', $instant), new PairRow('In a fight', self::share($pyroblast->fightCasts, $pyroblast->casts), Unit::Percent)]),
                self::split($share, 'Cast instantly under Presence of Mind', 'Cast with its full cast time'),
            ],
            'note' => null,
        ];
    }

    /** @return array{blocks: list<Block>, note: string|null} */
    private function cast(SpellTotals $spell): array
    {
        $hours = $this->rows->playedMs() / 3_600_000;
        $share = self::share($spell->fightCasts, $spell->casts);

        return [
            'blocks' => [
                new Pair([
                    new PairRow('Casts', $spell->casts),
                    new PairRow('In a fight', $share, Unit::Percent),
                    new PairRow('Hours between casts', $spell->casts === 0 ? 0 : $hours / $spell->casts, Unit::Decimal),
                ]),
                self::split($share, 'Cast in a fight', 'Cast out of a fight'),
            ],
            'note' => null,
        ];
    }

    /** @return list<int> the ids of the aura a talent's spell puts on the mage */
    private function auras(SpellTotals $spell): array
    {
        return array_map(fn (StateTotals $state): int => $state->refId, $this->rows->states(StateKind::OwnBuff)->of(StateKind::OwnBuff, $spell->name));
    }

    /**
     * What was cast while the spell's aura was on, by the spell's id.
     *
     * @return array<int, int>
     */
    private function under(SpellTotals $spell): array
    {
        $auras = $this->auras($spell);
        $by = [];
        foreach ($auras === [] ? [] : $this->rows->tallies()->of(Tally::CastUnder) as $row) {
            if (in_array($row->a, $auras, true)) {
                $by[$row->b] = ($by[$row->b] ?? 0) + $row->n;
            }
        }
        arsort($by);

        return $by;
    }

    /** @param  array<int, int>  $under */
    private function spentOn(array $under): ?Rank
    {
        if ($under === []) {
            return null;
        }
        $names = $this->rows->spellNames(array_keys($under));
        $by = [];
        foreach ($under as $id => $count) {
            $name = $names[$id] ?? null;
            if ($name !== null) {
                $by[$name] = ['count' => ($by[$name]['count'] ?? 0) + $count, 'id' => $by[$name]['id'] ?? $id];
            }
        }
        uasort($by, fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $rows = [];
        foreach (array_slice($by, 0, self::SPENT_ON, true) as $name => $one) {
            $rows[] = new RankRow($name, $one['count'], Icon::spell($one['id']), Tone::Own);
        }

        return $rows === [] ? null : new Rank($rows);
    }

    /** @return list<Block> */
    private static function with(Pair $pair, ?Rank $rank): array
    {
        return $rank === null ? [$pair] : [$pair, $rank];
    }

    private static function share(int $part, int $whole): int|float
    {
        return $whole === 0 ? 0 : min(100, $part / $whole * 100);
    }

    private static function split(int|float $share, string $yes, string $no): Split
    {
        return new Split([new SplitPart($yes, $share, Tone::Own), new SplitPart($no, 100 - $share, Tone::Lost)]);
    }
}
