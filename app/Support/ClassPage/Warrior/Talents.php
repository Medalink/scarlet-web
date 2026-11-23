<?php

namespace App\Support\ClassPage\Warrior;

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
use App\Support\ClassRows\SpellTotals;
use App\Support\ClassRows\Tally;
use App\Support\StateKind;

/** The modules of the warrior talents the latest build holds: Mortal Strike, Sweeping Strikes, Enrage, Death Wish, Flurry, Last Stand and Shield Slam. */
final readonly class Talents
{
    private const int DEATH_SECONDS = 10;

    private const int KILL_SECONDS = 3;

    private DamageRank $damage;

    public function __construct(private ClassRows $rows, private Spells $spells)
    {
        $this->damage = new DamageRank($rows);
    }

    /** @return list<TalentModule> one for each held talent that has a module, in the build's order */
    public function of(ClassFacts $facts): array
    {
        $modules = [];
        foreach ($facts->talents as $held) {
            $blocks = match ($held->name) {
                'Mortal Strike' => $this->struck($this->spells->mortalStrike),
                'Shield Slam' => $this->struck($this->spells->shieldSlam),
                'Sweeping Strikes' => $this->buff('Sweeping Strikes', 'Casts', $this->spells->sweeping->casts),
                'Enrage' => $this->buff('Enrage', 'Times enraged', null),
                'Flurry' => $this->buff('Flurry', 'Times it procced', null),
                'Death Wish' => $this->deathWish(),
                'Last Stand' => $this->lastStand(),
                default => null,
            };
            if ($blocks === null) {
                continue;
            }
            $modules[] = new TalentModule($held->name, $held->points, $held->max, $blocks, $held->spellId === 0 ? null : Icon::spell($held->spellId));
        }

        return $modules;
    }

    /** @return list<Block> casts, the share of the damage the meter listed to the spell and the share of casts that killed */
    private function struck(SpellTotals $spell): array
    {
        $killed = $spell->ids() === [] ? 0 : $this->rows->tallies()->count(Tally::KillBlow, $spell->ids());
        $share = $spell->casts === 0 ? 0 : min(100, $killed / $spell->casts * 100);

        return [
            new Pair([
                new PairRow('Casts', $spell->casts),
                new PairRow('Of your damage', $this->damage->share($spell->name), Unit::Percent),
                new PairRow('Killed within '.self::KILL_SECONDS.' seconds', $share, Unit::Percent),
            ]),
            self::split($share, 'The target died within '.self::KILL_SECONDS.' seconds', 'It lived'),
        ];
    }

    /**
     * @param  list<PairRow>  $extra  figures after the three every buff has
     * @return list<Block>
     */
    private function buff(string $name, string $first, ?int $casts, array $extra = []): array
    {
        $states = $this->rows->states()->of(StateKind::OwnBuff, $name);
        $starts = array_sum(array_map(fn ($state): int => $state->starts, $states));
        $fightMs = array_sum(array_map(fn ($state): int => $state->fightMs, $states));
        $fights = $this->rows->states()->fights()->ms;
        $up = $fights === 0 ? 0 : min(100, $fightMs / $fights * 100);
        $ids = array_map(fn ($state): int => $state->refId, $states);
        $under = $ids === [] ? 0 : $this->rows->tallies()->count(Tally::CastUnder, $ids);

        return [
            new Pair([new PairRow($first, $casts ?? $starts), new PairRow('Of fight time it was up', $up, Unit::Percent), new PairRow('Attacks under it', $under), ...$extra]),
            self::split($up, 'Fight time with it up', 'Fight time without'),
        ];
    }

    /** @return list<Block> */
    private function deathWish(): array
    {
        $wish = $this->spells->deathWish;
        $deaths = $wish->ids() === [] ? 0 : $this->rows->tallies()->count(Tally::DeathAfter, $wish->ids(), range(0, self::DEATH_SECONDS - 1));

        return $this->buff('Death Wish', 'Casts', $wish->casts, [new PairRow('Deaths within '.self::DEATH_SECONDS.' seconds of one', $deaths)]);
    }

    /** @return list<Block> */
    private function lastStand(): array
    {
        $stand = $this->spells->lastStand;
        $deaths = $stand->ids() === [] ? 0 : $this->rows->tallies()->count(Tally::DeathAfter, $stand->ids(), range(0, self::DEATH_SECONDS - 1));
        $died = $stand->casts === 0 ? 0 : min(100, $deaths / $stand->casts * 100);

        return [
            new Pair([new PairRow('Casts', $stand->casts), new PairRow('Used in a fight', $stand->casts === 0 ? 0 : $stand->fightCasts / $stand->casts * 100, Unit::Percent), new PairRow('Deaths within '.self::DEATH_SECONDS.' seconds of one', $deaths)]),
            self::split(100 - $died, 'No death within '.self::DEATH_SECONDS.' seconds', 'Died within '.self::DEATH_SECONDS.' seconds'),
        ];
    }

    private static function split(int|float $used, string $yes, string $no): Split
    {
        return new Split([new SplitPart($yes, $used, Tone::Own), new SplitPart($no, 100 - $used, Tone::Lost)]);
    }
}
