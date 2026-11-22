<?php

namespace App\View\Design;

use App\Support\PlayerClass;

/** The facts and callouts of a class page's head with invented numbers, for `SceneHead`: one callout on each model that carries an anchor. */
final class ClassSceneHead
{
    /** @return list<string> */
    public static function facts(): array
    {
        return ['Level 52', '212 hours played', '3,640 characters of the class'];
    }

    /** @return list<array{key: string, title: string, text: string, share: string}> */
    public static function callouts(PlayerClass $class): array
    {
        return match ($class) {
            PlayerClass::Warrior => [
                self::callout('dummy', 'Heroic Strike', 'The attack used most', '31% of attacks'),
                self::callout('weaponrack', 'One-Handed Swords', 'The weapon type fought with longest', '58% of hours'),
                self::callout('armorstand', 'Shield', 'Fights with a shield equipped', '44% of fights'),
            ],
            PlayerClass::Paladin => [
                self::callout('warhorse', 'Warhorse', 'Hours on the class mount', '62 hours, summoned 410 times'),
                self::callout('podium', 'Blessing of Might', 'The blessing cast most', '38% of blessings'),
                self::callout('shieldrack', 'Divine Shield', 'Divine Shields cast', '17, 5 in a Hearthstone'),
            ],
            PlayerClass::Hunter => [
                self::callout('cat', 'Cat', 'Hours out', '22% of pet time'),
                self::callout('wolf', 'Wolf', 'Hours out', '41% of pet time'),
                self::callout('boar', 'Boar', 'Hours out', '9% of pet time'),
                self::callout('owl', 'Owl', 'Hours out', '28% of pet time'),
            ],
            PlayerClass::Rogue => [
                self::callout('chest', 'Lockpicking', 'Locks picked', '212 locks'),
                self::callout('vials', 'Instant Poison', 'The poison applied most', '64% of poisons'),
                self::callout('sack', 'Pick Pocket', 'Gold lifted from pockets', '41 gold'),
            ],
            PlayerClass::Priest => [
                self::callout('podium', 'Flash Heal', 'The heal cast most', '36% of heals'),
                self::callout('altar', 'Resurrection', 'Players raised', '28 players'),
                self::callout('candelabra', 'Power Word: Shield', 'Shields cast', '1,940, 212,000 absorbed'),
            ],
            PlayerClass::Shaman => [
                self::callout('fire', 'Searing Totem', 'The most dropped fire totem', '412 drops, 31 hours'),
                self::callout('earth', 'Stoneskin Totem', 'The most dropped earth totem', '388 drops, 27 hours'),
                self::callout('water', 'Healing Stream Totem', 'The most dropped water totem', '301 drops, 22 hours'),
                self::callout('air', 'Windfury Totem', 'The most dropped air totem', '264 drops, 19 hours'),
            ],
            PlayerClass::Mage => [
                self::callout('portal', 'Portal: Stormwind', 'The city opened most', '62% of portals'),
                self::callout('sheep', 'Polymorph', 'Sheep made', '1,120 sheep'),
                self::callout('bottles', 'Conjure Water', 'Water conjured', '14,200 water, 3,100 handed to others'),
            ],
            PlayerClass::Warlock => [
                self::callout('imp', 'Imp', 'Hours out', '31% of demon time'),
                self::callout('voidwalker', 'Voidwalker', 'Hours out', '34% of demon time'),
                self::callout('succubus', 'Succubus', 'Hours out', '12% of demon time'),
                self::callout('felhunter', 'Felhunter', 'Hours out', '14% of demon time'),
            ],
            PlayerClass::Druid => [
                self::callout('bear', 'Bear Form', 'Hours worn', '38% of time in a form'),
                self::callout('cat', 'Cat Form', 'Hours worn', '41% of time in a form'),
                self::callout('travel', 'Travel Form', 'Hours worn', '16% of time in a form'),
                self::callout('aquatic', 'Aquatic Form', 'Hours worn', '5% of time in a form'),
            ],
        };
    }

    /** @return array{key: string, title: string, text: string, share: string} */
    private static function callout(string $key, string $title, string $text, string $share): array
    {
        return ['key' => $key, 'title' => $title, 'text' => $text, 'share' => $share];
    }
}
