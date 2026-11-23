<?php

namespace App\Support\ClassPage\Mage;

use App\Support\ClassPage\Icon;
use App\Support\ClassRows\SpellBook;
use App\Support\ClassRows\SpellTotals;

/** The mage spells the page reads, each folded over its ranks by the client's name. */
final readonly class Spells
{
    /** The school of each attack spell, by name: the colour of its bar and the series of the damage chart. */
    public const array FROST = ['Frostbolt', 'Cone of Cold', 'Blizzard', 'Frost Nova'];

    public const array FIRE = ['Fireball', 'Fire Blast', 'Scorch', 'Flamestrike', 'Pyroblast', 'Blast Wave', 'Combustion', 'Ignite'];

    public const array ARCANE = ['Arcane Missiles', 'Arcane Explosion'];

    public const array ATTACKS = ['Frostbolt', 'Fireball', 'Fire Blast', 'Arcane Missiles', 'Cone of Cold', 'Scorch', 'Arcane Explosion', 'Blizzard', 'Flamestrike', 'Pyroblast'];

    public function __construct(
        public SpellTotals $conjureWater,
        public SpellTotals $conjureFood,
        public SpellTotals $conjureGem,
        public SpellTotals $portals,
        public SpellTotals $teleports,
        public SpellTotals $polymorph,
        public SpellTotals $sheep,
        public SpellTotals $blink,
        public SpellTotals $frostNova,
        public SpellTotals $counterspell,
        public SpellTotals $iceBlock,
        public SpellTotals $manaShield,
        public SpellTotals $evocation,
        public SpellTotals $blizzard,
        public SpellTotals $intellect,
        public SpellTotals $removeCurse,
        public SpellTotals $drink,
        public SpellTotals $food,
        public SpellTotals $iceBarrier,
        public SpellTotals $coldSnap,
        public SpellTotals $presence,
        public SpellTotals $arcanePower,
        public SpellTotals $pyroblast,
        public SpellTotals $combustion,
    ) {}

    public static function of(SpellBook $book): self
    {
        return new self(
            $book->sum('Conjure Water', 'Conjure Water'),
            $book->sum('Conjure Food', 'Conjure Food'),
            $book->sum('Conjure Mana Gem', 'Conjure Mana *'),
            $book->sum('Portals', 'Portal: *'),
            $book->sum('Teleports', 'Teleport: *'),
            $book->sum('Polymorph', 'Polymorph', 'Polymorph: *'),
            $book->sum('Polymorph', 'Polymorph'),
            $book->sum('Blink', 'Blink'),
            $book->sum('Frost Nova', 'Frost Nova'),
            $book->sum('Counterspell', 'Counterspell'),
            $book->sum('Ice Block', 'Ice Block'),
            $book->sum('Mana Shield', 'Mana Shield'),
            $book->sum('Evocation', 'Evocation'),
            $book->sum('Blizzard', 'Blizzard'),
            $book->sum('Arcane Intellect', 'Arcane Intellect', 'Arcane Brilliance'),
            $book->sum('Remove Curse', 'Remove Lesser Curse', 'Remove Curse'),
            $book->sum('Drink', 'Drink'),
            $book->sum('Food', 'Food'),
            $book->sum('Ice Barrier', 'Ice Barrier'),
            $book->sum('Cold Snap', 'Cold Snap'),
            $book->sum('Presence of Mind', 'Presence of Mind'),
            $book->sum('Arcane Power', 'Arcane Power'),
            $book->sum('Pyroblast', 'Pyroblast'),
            $book->sum('Combustion', 'Combustion'),
        );
    }

    /** The icon of a spell total's most cast rank, or null when it was never cast. */
    public static function icon(SpellTotals $spell): ?Icon
    {
        return $spell->iconId === 0 ? null : Icon::spell($spell->iconId);
    }
}
