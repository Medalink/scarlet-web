<?php

namespace App\Support\ClassPage\Hunter;

use App\Support\ClassPage\Icon;
use App\Support\ClassRows\SpellBook;
use App\Support\ClassRows\SpellTotals;

/** The hunter spells the page reads, each folded over its ranks by the client's name. */
final readonly class Spells
{
    public const array TRAPS = ['Freezing Trap', 'Immolation Trap', 'Explosive Trap', 'Frost Trap', 'Snake Trap'];

    public const array SHOTS = ['Arcane Shot', 'Multi-Shot', 'Serpent Sting', 'Concussive Shot', 'Aimed Shot', "Hunter's Mark"];

    public const array ASPECTS = ['Aspect of the Hawk', 'Aspect of the Monkey', 'Aspect of the Cheetah', 'Aspect of the Pack', 'Aspect of the Wild', 'Aspect of the Beast'];

    public function __construct(
        public SpellTotals $traps,
        public SpellTotals $freezing,
        public SpellTotals $feign,
        public SpellTotals $tame,
        public SpellTotals $feed,
        public SpellTotals $revive,
        public SpellTotals $mend,
        public SpellTotals $tranquilizing,
        public SpellTotals $shots,
        public SpellTotals $aimed,
        public SpellTotals $bestialWrath,
        public SpellTotals $intimidation,
        public SpellTotals $scatter,
        public SpellTotals $deterrence,
        public SpellTotals $counterattack,
        public SpellTotals $pack,
        public SpellBook $book,
    ) {}

    public static function of(SpellBook $book): self
    {
        return new self(
            $book->sum('Traps', ...self::TRAPS),
            $book->sum('Freezing Trap', 'Freezing Trap'),
            $book->sum('Feign Death', 'Feign Death'),
            $book->sum('Tame Beast', 'Tame Beast'),
            $book->sum('Feed Pet', 'Feed Pet'),
            $book->sum('Revive Pet', 'Revive Pet'),
            $book->sum('Mend Pet', 'Mend Pet'),
            $book->sum('Tranquilizing Shot', 'Tranquilizing Shot'),
            $book->sum('Shots', ...self::SHOTS),
            $book->sum('Aimed Shot', 'Aimed Shot'),
            $book->sum('Bestial Wrath', 'Bestial Wrath'),
            $book->sum('Intimidation', 'Intimidation'),
            $book->sum('Scatter Shot', 'Scatter Shot'),
            $book->sum('Deterrence', 'Deterrence'),
            $book->sum('Counterattack', 'Counterattack'),
            $book->sum('Aspect of the Pack', 'Aspect of the Pack'),
            $book,
        );
    }

    /** The icon of a spell total's most cast rank, or null when it was never cast. */
    public static function icon(SpellTotals $spell): ?Icon
    {
        return $spell->iconId === 0 ? null : Icon::spell($spell->iconId);
    }
}
