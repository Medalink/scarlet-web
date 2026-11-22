<?php

namespace App\Support\ClassPage;

use App\Support\PlayerClass;

/**
 * The talents of each class that get a module on its page, by the client's name of the talent's spell (docs/DESIGN.md,
 * Class pages, "Talent modules for the builds the mocks show"). A talent that only adds a percentage gets none. A module
 * is drawn while the character's latest build holds the talent, so a respec changes that section and nothing else.
 */
final class TalentModules
{
    /** The most modules a page shows. */
    public const int MOST = 3;

    /** @var array<string, list<string>> class value => talent spell names */
    public const array BY_CLASS = [
        PlayerClass::Warrior->value => ['Mortal Strike', 'Sweeping Strikes', 'Enrage'],
        PlayerClass::Paladin->value => ['Seal of Command', 'Repentance', 'Consecration'],
        PlayerClass::Hunter->value => ['Bestial Wrath', 'Intimidation', 'Aimed Shot'],
        PlayerClass::Rogue->value => ['Blade Flurry', 'Adrenaline Rush', 'Riposte'],
        PlayerClass::Priest->value => ['Inner Focus', 'Spirit of Redemption', 'Lightwell'],
        PlayerClass::Shaman->value => ['Stormstrike', 'Elemental Focus', 'Flurry'],
        PlayerClass::Mage->value => ['Ice Barrier', 'Cold Snap', 'Ice Block'],
        PlayerClass::Warlock->value => ['Nightfall', 'Shadowburn', 'Siphon Life'],
        PlayerClass::Druid->value => ['Feral Charge (Bear)', 'Omen of Clarity', 'Leader of the Pack'],
    ];

    /** @return list<string> */
    public static function of(PlayerClass $class): array
    {
        return self::BY_CLASS[$class->value];
    }
}
