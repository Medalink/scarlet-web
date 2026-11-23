<?php

namespace App\Support\ClassPage\Shaman;

use App\Support\ClassRows\SpellBook;
use App\Support\ClassRows\SpellTotals;

/** The shaman spells and the totem slots the page reads, each spell folded over its ranks by the client's name. */
final readonly class Spells
{
    /** The totem slot of each element and the colour of its charts. */
    public const array ELEMENTS = [
        1 => ['Fire', '#ef6a2d'],
        2 => ['Earth', '#b9985a'],
        3 => ['Water', '#4cc3d9'],
        4 => ['Air', '#b7c3d8'],
    ];

    public const array SHOCKS = ['Earth Shock', 'Flame Shock', 'Frost Shock'];

    public const array IMBUES = ['Windfury Weapon', 'Frostbrand Weapon', 'Flametongue Weapon', 'Rockbiter Weapon'];

    public const array HEALS = ['Healing Wave', 'Chain Heal', 'Lesser Healing Wave', 'Ancestral Spirit'];

    public const array CURES = ['Purge', 'Cure Poison', 'Cure Disease'];

    public const array GROUP_TOTEMS = ['Windfury Totem', 'Strength of Earth Totem', 'Mana Spring Totem', 'Grace of Air Totem', 'Tremor Totem'];

    public const string WOLF = 'Ghost Wolf';

    public const string ANKH = 'Ankh';

    public const int MANA_TIDE = 16190;

    public const int MOUNT_ITEM = 1132;

    public function __construct(
        public SpellTotals $reincarnation,
        public SpellTotals $recall,
        public SpellTotals $earthShock,
    ) {}

    public static function of(SpellBook $book): self
    {
        return new self(
            $book->sum('Reincarnation', 'Reincarnation'),
            $book->sum('Astral Recall', 'Astral Recall'),
            $book->sum('Earth Shock', 'Earth Shock'),
        );
    }
}
