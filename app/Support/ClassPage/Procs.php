<?php

namespace App\Support\ClassPage;

use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\SpellTotals;
use App\Support\ClassRows\StateTotals;
use App\Support\ClassRows\Tally;
use App\Support\StateKind;
use App\View\Format;

/**
 * The blocks of a talent module for a talent a character sees work: a spell it casts, a buff it gains, or the proc that
 * the next cast spends. The casts under an aura come from the `cast_under` tally, which watches the auras listed in
 * docs/ROWS.md.
 */
final readonly class Procs
{
    public function __construct(private ClassRows $rows) {}

    /**
     * The aura of a name on the character, from its own buffs and the buffs others gave it, with the spell ids it went by.
     *
     * @return array{ids: list<int>, starts: int, ms: int, fightMs: int}
     */
    public function aura(string $name): array
    {
        $states = [...$this->rows->states()->of(StateKind::OwnBuff, $name), ...$this->rows->states()->of(StateKind::OthersBuff, $name)];

        return [
            'ids' => array_values(array_unique(array_map(fn (StateTotals $state): int => $state->refId, $states))),
            'starts' => array_sum(array_map(fn (StateTotals $state): int => $state->starts, $states)),
            'ms' => array_sum(array_map(fn (StateTotals $state): int => $state->ms, $states)),
            'fightMs' => array_sum(array_map(fn (StateTotals $state): int => $state->fightMs, $states)),
        ];
    }

    /**
     * Casts made while one of these auras was on, or up to 0.3 seconds after it ended.
     *
     * @param  list<int>  $auras
     * @param  list<int>  $spells  only these spells; any spell when empty
     */
    public function under(array $auras, array $spells = []): int
    {
        return $auras === [] ? 0 : $this->rows->tallies()->count(Tally::CastUnder, $auras, $spells);
    }

    /**
     * A spell cast: how often, how much of it in a fight, and the longest run.
     *
     * @return list<Block>|null null when it was never cast
     */
    public function cast(SpellTotals $spell): ?array
    {
        if ($spell->casts === 0) {
            return null;
        }
        $fight = Casts::share($spell->fightCasts, $spell->casts);

        return [
            new Pair([new PairRow('Casts', $spell->casts), new PairRow('Cast in a fight', $fight, Unit::Percent), new PairRow('Most in a row', $spell->streak)]),
            self::split($fight, 'Cast in a fight', 'Cast out of a fight'),
        ];
    }

    /**
     * A spell that puts a buff on the character: casts, hours the buff was up and what was cast under it.
     *
     * @return list<Block>|null null when it was never cast
     */
    public function buffed(SpellTotals $spell, string $aura): ?array
    {
        if ($spell->casts === 0) {
            return null;
        }
        $buff = $this->aura($aura);
        $blocks = [new Pair([
            new PairRow('Casts', $spell->casts),
            new PairRow('Time up', self::time($buff['ms'] / 1000)),
            new PairRow('Casts under it', $this->under($buff['ids'])),
        ])];
        if ($buff['ms'] > 0) {
            $blocks[] = self::split(Casts::share($buff['fightMs'], $buff['ms']), 'Time up in a fight', 'Time up out of a fight');
        }

        return $blocks;
    }

    /**
     * A buff that comes up on its own and is spent by the next cast: how often, how many casts spent it, how many ran out.
     *
     * @return list<Block>|null null when it never came up
     */
    public function proc(string $aura, string $names = 'a spell'): ?array
    {
        $buff = $this->aura($aura);
        if ($buff['starts'] === 0) {
            return null;
        }
        $spent = min($buff['starts'], $this->under($buff['ids']));
        $used = Casts::share($spent, $buff['starts']);

        return [
            new Pair([new PairRow('Times it came up', $buff['starts']), new PairRow('Spent on '.$names, $spent), new PairRow('Used', $used, Unit::Percent)]),
            self::split($used, 'Spent on '.$names, 'Ran out'),
        ];
    }

    /**
     * A buff that comes up on its own: how often, how long and what was cast under it.
     *
     * @return list<Block>|null null when it never came up
     */
    public function held(string $aura, string $under): ?array
    {
        $buff = $this->aura($aura);
        if ($buff['starts'] === 0) {
            return null;
        }

        return [
            new Pair([new PairRow('Times it came up', $buff['starts']), new PairRow('Time up', self::time($buff['ms'] / 1000)), new PairRow($under, $this->under($buff['ids']))]),
            self::split(Casts::share($buff['fightMs'], $buff['ms']), 'Time up in a fight', 'Time up out of a fight'),
        ];
    }

    /**
     * A buff that comes up on its own and that no cast the rows watch spends: how often it came up and for how long.
     *
     * @return list<Block>|null null when it never came up
     */
    public function comes(string $aura): ?array
    {
        $buff = $this->aura($aura);
        if ($buff['starts'] === 0) {
            return null;
        }

        return [
            new Pair([new PairRow('Times it came up', $buff['starts']), new PairRow('Time up', self::time($buff['ms'] / 1000))]),
            self::split(Casts::share($buff['fightMs'], $buff['ms']), 'Time up in a fight', 'Time up out of a fight'),
        ];
    }

    /**
     * A spell cast under an aura of its own: how many of its casts were made under it.
     *
     * @return list<Block>|null null when the spell was never cast
     */
    public function castUnder(SpellTotals $spell, string $aura, string $what): ?array
    {
        if ($spell->casts === 0) {
            return null;
        }
        $under = min($spell->casts, $this->under($this->aura($aura)['ids'], $spell->ids()));
        $share = Casts::share($under, $spell->casts);

        return [
            new Pair([new PairRow('Casts', $spell->casts), new PairRow('Cast '.$what, $under), new PairRow('Share', $share, Unit::Percent)]),
            self::split($share, 'Cast '.$what, 'Cast without it'),
        ];
    }

    /** Seconds as "45 s" up to two minutes, then as the comps write a played time. */
    private static function time(int|float $seconds): string
    {
        return $seconds < 120 ? Unit::Count->say($seconds).' s' : Format::duration($seconds);
    }

    public static function split(int|float $share, string $yes, string $no): Split
    {
        return new Split([new SplitPart($yes, $share, Tone::Own), new SplitPart($no, 100 - $share, Tone::Lost)]);
    }
}
