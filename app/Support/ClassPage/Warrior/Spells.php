<?php

namespace App\Support\ClassPage\Warrior;

use App\Support\ClassPage\Icon;
use App\Support\ClassRows\SpellBook;
use App\Support\ClassRows\SpellTotals;

/** The warrior spells the page reads, each folded over its ranks by the client's name. */
final readonly class Spells
{
    /** The attacks of the "Attacks used" rank, by the client's names; Execute has a module of its own. */
    public const array ATTACKS = ['Heroic Strike', 'Mortal Strike', 'Bloodthirst', 'Rend', 'Revenge', 'Overpower', 'Thunder Clap', 'Hamstring', 'Cleave', 'Slam', 'Whirlwind', 'Shield Slam', 'Concussion Blow'];

    public const array STANCES = ['Battle Stance', 'Defensive Stance', 'Berserker Stance'];

    public const array SHOUTS = ['Battle Shout', 'Demoralizing Shout', 'Intimidating Shout', 'Challenging Shout', 'Piercing Howl'];

    public const array HOLDING = ['Taunt', 'Sunder Armor', 'Shield Block', 'Revenge', 'Mocking Blow'];

    public const array INTERRUPTS = ['Pummel', 'Shield Bash'];

    public function __construct(
        public SpellTotals $charges,
        public SpellTotals $execute,
        public SpellTotals $sunder,
        public SpellTotals $battleShout,
        public SpellTotals $taunt,
        public SpellTotals $stances,
        public SpellTotals $mortalStrike,
        public SpellTotals $bloodthirst,
        public SpellTotals $deathWish,
        public SpellTotals $lastStand,
        public SpellTotals $shieldSlam,
        public SpellTotals $sweeping,
        public SpellBook $book,
    ) {}

    public static function of(SpellBook $book): self
    {
        return new self(
            $book->sum('Charges', 'Charge', 'Intercept'),
            $book->sum('Execute', 'Execute'),
            $book->sum('Sunder Armor', 'Sunder Armor'),
            $book->sum('Battle Shout', 'Battle Shout'),
            $book->sum('Taunt', 'Taunt'),
            $book->sum('Stance changes', ...self::STANCES),
            $book->sum('Mortal Strike', 'Mortal Strike'),
            $book->sum('Bloodthirst', 'Bloodthirst'),
            $book->sum('Death Wish', 'Death Wish'),
            $book->sum('Last Stand', 'Last Stand'),
            $book->sum('Shield Slam', 'Shield Slam'),
            $book->sum('Sweeping Strikes', 'Sweeping Strikes'),
            $book,
        );
    }

    /** The icon of a spell total's most cast rank, or null when it was never cast. */
    public static function icon(SpellTotals $spell): ?Icon
    {
        return $spell->iconId === 0 ? null : Icon::spell($spell->iconId);
    }
}
