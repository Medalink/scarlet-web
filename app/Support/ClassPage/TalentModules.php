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
        PlayerClass::Warrior->value => ['Mortal Strike', 'Sweeping Strikes', 'Enrage', 'Death Wish', 'Flurry', 'Last Stand', 'Shield Slam'],
        PlayerClass::Paladin->value => ['Seal of Command', 'Repentance', 'Consecration', 'Divine Favor', 'Holy Shock', 'Holy Shield', 'Blessing of Kings'],
        PlayerClass::Hunter->value => ['Bestial Wrath', 'Intimidation', 'Aimed Shot', 'Scatter Shot', 'Deterrence', 'Counterattack'],
        PlayerClass::Rogue->value => ['Blade Flurry', 'Adrenaline Rush', 'Riposte', 'Cold Blood', 'Remorseless Attacks', 'Ghostly Strike', 'Premeditation'],
        PlayerClass::Priest->value => ['Inner Focus', 'Spirit of Redemption', 'Lightwell', 'Power Infusion', 'Shadowform', 'Vampiric Embrace'],
        PlayerClass::Shaman->value => ['Stormstrike', 'Elemental Focus', 'Flurry', 'Lightning Mastery', "Nature's Swiftness", 'Mana Tide Totem'],
        PlayerClass::Mage->value => ['Ice Barrier', 'Cold Snap', 'Ice Block', 'Presence of Mind', 'Arcane Power', 'Pyroblast', 'Combustion'],
        PlayerClass::Warlock->value => ['Nightfall', 'Shadowburn', 'Siphon Life', 'Fel Domination', 'Demonic Sacrifice', 'Soul Link', 'Conflagrate'],
        PlayerClass::Druid->value => ['Feral Charge (Bear)', 'Omen of Clarity', 'Leader of the Pack', "Nature's Grasp", 'Moonkin Form', "Nature's Swiftness", 'Swiftmend'],
    ];

    /** @return list<string> */
    public static function of(PlayerClass $class): array
    {
        return self::BY_CLASS[$class->value];
    }
}
