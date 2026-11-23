<?php

namespace App\Support\ClassPage\Paladin;

use App\Support\ClassPage\Block;
use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\Common\DamageRank;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Split;
use App\Support\ClassPage\SplitPart;
use App\Support\ClassPage\TalentModule;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Meter;
use App\Support\ClassRows\SpellTotals;
use App\Support\StateKind;

/** The modules of the paladin talents the latest build holds: Seal of Command, Repentance, Consecration, Divine Favor, Holy Shock, Holy Shield and Blessing of Kings. */
final readonly class Talents
{
    private DamageRank $damage;

    public function __construct(private ClassRows $rows, private Spells $spells, private Seals $seals)
    {
        $this->damage = new DamageRank($rows);
    }

    /** @return list<TalentModule> one for each held talent that has a module, in the build's order */
    public function of(ClassFacts $facts): array
    {
        $modules = [];
        foreach ($facts->talents as $held) {
            $blocks = match ($held->name) {
                'Seal of Command' => $this->command(),
                'Repentance' => $this->used($this->spells->repentance, [new PairRow('Casts a played hour', $this->perHour($this->spells->repentance->casts), Unit::Decimal)]),
                'Consecration' => $this->used($this->spells->consecration, [new PairRow('Casts a fight', $this->perFight($this->spells->consecration->casts), Unit::Decimal)]),
                'Divine Favor' => $this->used($this->spells->divineFavor, [new PairRow('Casts a played hour', $this->perHour($this->spells->divineFavor->casts), Unit::Decimal)]),
                'Holy Shock' => $this->used($this->spells->holyShock, [new PairRow('Of the healing listed', $this->healing('Holy Shock'), Unit::Percent)]),
                'Holy Shield' => $this->holyShield(),
                'Blessing of Kings' => $this->kings(),
                default => null,
            };
            if ($blocks === null) {
                continue;
            }
            $modules[] = new TalentModule($held->name, $held->points, $held->max, $blocks, $held->spellId === 0 ? null : Icon::spell($held->spellId));
        }

        return $modules;
    }

    /** @return list<Block> */
    private function command(): array
    {
        $own = $this->seals->of('Seal of Command');
        $judged = $own['put'] === 0 ? 0 : $own['judged'] / $own['put'] * 100;

        return [
            new Pair([
                new PairRow('Seals put up', $own['put']),
                new PairRow('Judged', $judged, Unit::Percent),
                new PairRow('Of your damage', $this->damage->share('Seal of Command'), Unit::Percent),
            ]),
            self::split($judged, 'Judged', 'Ran out, replaced or still up'),
        ];
    }

    /**
     * @param  list<PairRow>  $extra  the third figure
     * @return list<Block>
     */
    private function used(SpellTotals $spell, array $extra): array
    {
        $used = $spell->casts === 0 ? 0 : $spell->fightCasts / $spell->casts * 100;

        return [
            new Pair([new PairRow('Casts', $spell->casts), new PairRow('Used in a fight', $used, Unit::Percent), ...$extra]),
            self::split($used, 'Casts in a fight', 'Casts outside one'),
        ];
    }

    /** @return list<Block> */
    private function holyShield(): array
    {
        $casts = $this->rows->spells()->sum('Holy Shield', 'Holy Shield')->casts;
        $state = $this->rows->states(StateKind::OwnBuff)->sum(StateKind::OwnBuff, 'Holy Shield');
        $fights = $this->rows->states(StateKind::Fight)->fights()->ms;
        $up = $fights === 0 ? 0 : min(100, $state->fightMs / $fights * 100);

        return [
            new Pair([new PairRow('Casts', $casts), new PairRow('Of fight time it was up', $up, Unit::Percent), new PairRow('Hours up', $state->hours(), Unit::Hours)]),
            self::split($up, 'Fight time with it up', 'Fight time without'),
        ];
    }

    /** @return list<Block> */
    private function kings(): array
    {
        $kings = $this->spells->kings->casts;
        $all = $this->spells->blessingCasts();
        $share = $all === 0 ? 0 : $kings / $all * 100;

        return [
            new Pair([new PairRow('Casts', $kings), new PairRow('Of your blessings', $share, Unit::Percent), new PairRow('Casts a played hour', $this->perHour($kings), Unit::Decimal)]),
            self::split($share, 'Blessings of Kings', 'Other blessings'),
        ];
    }

    private function perHour(int $casts): float
    {
        $played = $this->rows->playedMs();

        return $played === 0 ? 0 : $casts / ($played / 3_600_000);
    }

    private function perFight(int $casts): float
    {
        $fights = $this->rows->states(StateKind::Fight)->fights()->starts;

        return $fights === 0 ? 0 : $casts / $fights;
    }

    private function healing(string $name): float
    {
        $whole = $this->rows->meters()->total(Meter::Healing);
        foreach ($this->rows->meters()->spells(Meter::Healing) as $spell) {
            if ($spell->name === $name) {
                return $whole === 0 ? 0 : min(100, $spell->amount / $whole * 100);
            }
        }

        return 0;
    }

    private static function split(int|float $used, string $yes, string $no): Split
    {
        return new Split([new SplitPart($yes, $used, Tone::Own), new SplitPart($no, 100 - $used, Tone::Lost)]);
    }
}
