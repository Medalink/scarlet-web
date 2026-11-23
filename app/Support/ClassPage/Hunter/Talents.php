<?php

namespace App\Support\ClassPage\Hunter;

use App\Support\ClassPage\Block;
use App\Support\ClassPage\ClassFacts;
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

/** The modules of the hunter talents the latest build holds: Bestial Wrath, Intimidation, Aimed Shot, Scatter Shot, Deterrence and Counterattack. */
final readonly class Talents
{
    private const int KILL_SECONDS = 3;

    private const int DEATH_SECONDS = 10;

    public function __construct(private ClassRows $rows, private Spells $spells) {}

    /** @return list<TalentModule> one for each held talent that has a module, in the build's order */
    public function of(ClassFacts $facts): array
    {
        $modules = [];
        foreach ($facts->talents as $held) {
            $blocks = match ($held->name) {
                'Bestial Wrath' => $this->used($this->spells->bestialWrath, new PairRow('Casts a fight', $this->perFight($this->spells->bestialWrath->casts), Unit::Decimal)),
                'Intimidation' => $this->used($this->spells->intimidation, new PairRow('Casts a fight', $this->perFight($this->spells->intimidation->casts), Unit::Decimal)),
                'Aimed Shot' => $this->aimed(),
                'Scatter Shot' => $this->used($this->spells->scatter, new PairRow('Casts a played hour', $this->perHour($this->spells->scatter->casts), Unit::Decimal)),
                'Deterrence' => $this->used($this->spells->deterrence, new PairRow('Deaths within '.self::DEATH_SECONDS.' seconds of one', $this->deathsAfter($this->spells->deterrence))),
                'Counterattack' => $this->used($this->spells->counterattack, new PairRow('Casts a played hour', $this->perHour($this->spells->counterattack->casts), Unit::Decimal)),
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
    private function used(SpellTotals $spell, PairRow $third): array
    {
        $used = $spell->casts === 0 ? 0 : $spell->fightCasts / $spell->casts * 100;

        return [
            new Pair([new PairRow('Casts', $spell->casts), new PairRow('Used in a fight', $used, Unit::Percent), $third]),
            self::split($used, 'Casts in a fight', 'Casts outside one'),
        ];
    }

    /** @return list<Block> */
    private function aimed(): array
    {
        $aimed = $this->spells->aimed;
        $killed = $aimed->ids() === [] ? 0 : $this->rows->tallies()->count(Tally::KillBlow, $aimed->ids());
        $killedShare = $aimed->casts === 0 ? 0 : min(100, $killed / $aimed->casts * 100);
        $shots = $this->spells->shots->casts;

        return [
            new Pair([
                new PairRow('Casts', $aimed->casts),
                new PairRow('Of your shots', $shots === 0 ? 0 : $aimed->casts / $shots * 100, Unit::Percent),
                new PairRow('Killed within '.self::KILL_SECONDS.' seconds', $killedShare, Unit::Percent),
            ]),
            self::split($killedShare, 'The target died within '.self::KILL_SECONDS.' seconds', 'It lived'),
        ];
    }

    private function deathsAfter(SpellTotals $spell): int
    {
        return $spell->ids() === [] ? 0 : $this->rows->tallies()->count(Tally::DeathAfter, $spell->ids(), range(0, self::DEATH_SECONDS - 1));
    }

    private function perHour(int $casts): float
    {
        $played = $this->rows->playedMs();

        return $played === 0 ? 0 : $casts / ($played / 3_600_000);
    }

    private function perFight(int $casts): float
    {
        $fights = $this->rows->states()->fights()->starts;

        return $fights === 0 ? 0 : $casts / $fights;
    }

    private static function split(int|float $used, string $yes, string $no): Split
    {
        return new Split([new SplitPart($yes, $used, Tone::Own), new SplitPart($no, 100 - $used, Tone::Lost)]);
    }
}
