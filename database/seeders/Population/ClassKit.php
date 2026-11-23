<?php

namespace Database\Seeders\Population;

use App\Support\PlayerClass;

/**
 * What each class casts: the signature spells, auras and one talent build per class, with the ids and names the
 * game uses (the same tables the e2e class fixtures read). One build per class; the ranks and spent points a
 * character holds vary around it.
 */
final class ClassKit
{
    /**
     * spell id => name, the rotation and the finishers; the first entry is the opener a fight starts with.
     *
     * @return array<int, string>
     */
    public static function spells(PlayerClass $class): array
    {
        return match ($class) {
            PlayerClass::Warrior => [
                11578 => 'Charge', 20662 => 'Execute', 11597 => 'Sunder Armor', 11551 => 'Battle Shout',
                11574 => 'Rend', 11601 => 'Revenge', 11585 => 'Overpower', 1680 => 'Whirlwind',
                23881 => 'Bloodthirst', 21553 => 'Mortal Strike', 11608 => 'Cleave', 6552 => 'Pummel',
                2687 => 'Bloodrage', 2764 => 'Throw',
            ],
            PlayerClass::Paladin => [
                20271 => 'Judgement', 20154 => 'Seal of Righteousness', 20375 => 'Seal of Command',
                19740 => 'Blessing of Might', 19742 => 'Blessing of Wisdom', 465 => 'Devotion Aura',
                7294 => 'Retribution Aura', 26573 => 'Consecration', 24239 => 'Hammer of Wrath',
                853 => 'Hammer of Justice', 635 => 'Holy Light', 19750 => 'Flash of Light',
            ],
            PlayerClass::Hunter => [
                75 => 'Auto Shot', 3044 => 'Arcane Shot', 2643 => 'Multi-Shot', 1978 => 'Serpent Sting',
                19434 => 'Aimed Shot', 5116 => 'Concussive Shot', 1130 => 'Hunter\'s Mark',
                13165 => 'Aspect of the Hawk', 5384 => 'Feign Death', 136 => 'Mend Pet',
            ],
            PlayerClass::Rogue => [
                1752 => 'Sinister Strike', 2098 => 'Eviscerate', 53 => 'Backstab', 5171 => 'Slice and Dice',
                1943 => 'Rupture', 408 => 'Kidney Shot', 1784 => 'Stealth', 8676 => 'Ambush',
                8631 => 'Garrote', 6770 => 'Sap', 1776 => 'Gouge', 16511 => 'Hemorrhage',
            ],
            PlayerClass::Priest => [
                585 => 'Smite', 589 => 'Shadow Word: Pain', 8092 => 'Mind Blast', 15407 => 'Mind Flay',
                14914 => 'Holy Fire', 2061 => 'Flash Heal', 2054 => 'Heal', 139 => 'Renew', 17 => 'Power Word: Shield',
                588 => 'Inner Fire', 1243 => 'Power Word: Fortitude', 5019 => 'Shoot',
            ],
            PlayerClass::Shaman => [
                8042 => 'Earth Shock', 8050 => 'Flame Shock', 8056 => 'Frost Shock', 17364 => 'Stormstrike',
                403 => 'Lightning Bolt', 2075 => 'Searing Totem', 8187 => 'Magma Totem', 8075 => 'Strength of Earth Totem',
                5394 => 'Healing Stream Totem', 8024 => 'Flametongue Weapon', 331 => 'Healing Wave', 974 => 'Rockbiter Weapon',
            ],
            PlayerClass::Mage => [
                133 => 'Fireball', 5143 => 'Arcane Missiles', 2136 => 'Fire Blast', 122 => 'Frost Nova',
                10 => 'Blizzard', 2948 => 'Scorch', 11366 => 'Pyroblast', 1953 => 'Blink',
                118 => 'Polymorph', 1459 => 'Arcane Intellect', 1463 => 'Mana Shield', 430 => 'Drink',
            ],
            PlayerClass::Warlock => [
                11661 => 'Shadow Bolt', 11672 => 'Corruption', 11668 => 'Immolate', 1490 => 'Curse of the Elements',
                18265 => 'Siphon Life', 18871 => 'Shadowburn', 6215 => 'Fear', 17926 => 'Death Coil',
                1454 => 'Life Tap', 688 => 'Summon Imp', 697 => 'Summon Voidwalker', 698 => 'Ritual of Summoning',
            ],
            PlayerClass::Druid => [
                5176 => 'Wrath', 8921 => 'Moonfire', 5221 => 'Shred', 1079 => 'Rip', 1822 => 'Rake',
                22568 => 'Ferocious Bite', 768 => 'Cat Form', 5487 => 'Bear Form', 6807 => 'Maul',
                5185 => 'Healing Touch', 774 => 'Rejuvenation', 8936 => 'Regrowth', 770 => 'Faerie Fire',
            ],
        };
    }

    /** spell id => seconds the aura runs, the buffs the class keeps up on itself. */
    public static function auras(PlayerClass $class): array
    {
        return match ($class) {
            PlayerClass::Warrior => [11551 => 120, 2687 => 20],
            PlayerClass::Paladin => [20154 => 30, 19740 => 300, 465 => 120],
            PlayerClass::Hunter => [13165 => 120, 1130 => 120],
            PlayerClass::Rogue => [5171 => 15, 1784 => 1800],
            PlayerClass::Priest => [588 => 1800, 1243 => 1800, 139 => 15],
            PlayerClass::Shaman => [8024 => 300, 8075 => 120],
            PlayerClass::Mage => [1459 => 1800, 1463 => 60],
            PlayerClass::Warlock => [11672 => 12, 1490 => 300, 11708 => 120],
            PlayerClass::Druid => [8921 => 12, 770 => 40, 768 => 1],
        };
    }

    /**
     * The second spell a fight rotates to, the finisher a kill ends on and the id of the pet the class fights
     * beside, 0 when it fights alone.
     */
    public static function shape(PlayerClass $class): array
    {
        return match ($class) {
            PlayerClass::Warrior => ['finisher' => 20662, 'pet' => 0],
            PlayerClass::Paladin => ['finisher' => 20271, 'pet' => 0],
            PlayerClass::Hunter => ['finisher' => 2643, 'pet' => 990201],
            PlayerClass::Rogue => ['finisher' => 2098, 'pet' => 0],
            PlayerClass::Priest => ['finisher' => 8092, 'pet' => 0],
            PlayerClass::Shaman => ['finisher' => 8042, 'pet' => 990202],
            PlayerClass::Mage => ['finisher' => 2136, 'pet' => 990203],
            PlayerClass::Warlock => ['finisher' => 18871, 'pet' => 990204],
            PlayerClass::Druid => ['finisher' => 22568, 'pet' => 0],
        };
    }

    /**
     * One talent build: the tree id, the client's class id, the spec name, the three tabs and the nodes
     * `{node, spell, ranks, tab}` a character of the build holds.
     *
     * @return array{tree: int, classId: int, spec: string, tabs: array<int, array{int, string}>, nodes: list<array{node: int, spell: int, ranks: int, tab: int}>}
     */
    public static function build(PlayerClass $class): array
    {
        return match ($class) {
            PlayerClass::Warrior => [
                'tree' => 1001, 'classId' => 1, 'spec' => 'Arms',
                'tabs' => [[161, 'Arms'], [164, 'Fury'], [163, 'Protection']],
                'nodes' => [
                    ['node' => 1001, 'spell' => 12294, 'ranks' => 1, 'tab' => 161],
                    ['node' => 1002, 'spell' => 12292, 'ranks' => 1, 'tab' => 161],
                    ['node' => 1003, 'spell' => 12317, 'ranks' => 5, 'tab' => 164],
                ],
            ],
            PlayerClass::Paladin => [
                'tree' => 1002, 'classId' => 2, 'spec' => 'Retribution',
                'tabs' => [[381, 'Holy'], [382, 'Protection'], [383, 'Retribution']],
                'nodes' => [
                    ['node' => 2001, 'spell' => 20375, 'ranks' => 1, 'tab' => 383],
                    ['node' => 2002, 'spell' => 20218, 'ranks' => 5, 'tab' => 383],
                    ['node' => 2003, 'spell' => 20066, 'ranks' => 1, 'tab' => 381],
                ],
            ],
            PlayerClass::Hunter => [
                'tree' => 1003, 'classId' => 3, 'spec' => 'Beast Mastery',
                'tabs' => [[361, 'Beast Mastery'], [363, 'Marksmanship'], [362, 'Survival']],
                'nodes' => [
                    ['node' => 3001, 'spell' => 19574, 'ranks' => 1, 'tab' => 361],
                    ['node' => 3002, 'spell' => 19577, 'ranks' => 1, 'tab' => 361],
                    ['node' => 3003, 'spell' => 19506, 'ranks' => 1, 'tab' => 363],
                ],
            ],
            PlayerClass::Rogue => [
                'tree' => 5101, 'classId' => 4, 'spec' => 'Combat',
                'tabs' => [[182, 'Assassination'], [181, 'Combat'], [183, 'Subtlety']],
                'nodes' => [
                    ['node' => 4001, 'spell' => 13877, 'ranks' => 1, 'tab' => 181],
                    ['node' => 4002, 'spell' => 13750, 'ranks' => 1, 'tab' => 181],
                    ['node' => 4003, 'spell' => 14251, 'ranks' => 1, 'tab' => 181],
                ],
            ],
            PlayerClass::Priest => [
                'tree' => 5201, 'classId' => 5, 'spec' => 'Holy',
                'tabs' => [[201, 'Discipline'], [202, 'Holy'], [203, 'Shadow']],
                'nodes' => [
                    ['node' => 4101, 'spell' => 14751, 'ranks' => 1, 'tab' => 201],
                    ['node' => 4102, 'spell' => 20711, 'ranks' => 1, 'tab' => 202],
                    ['node' => 4103, 'spell' => 724, 'ranks' => 1, 'tab' => 202],
                ],
            ],
            PlayerClass::Shaman => [
                'tree' => 5301, 'classId' => 7, 'spec' => 'Enhancement',
                'tabs' => [[261, 'Elemental'], [263, 'Enhancement'], [262, 'Restoration']],
                'nodes' => [
                    ['node' => 4201, 'spell' => 17364, 'ranks' => 1, 'tab' => 263],
                    ['node' => 4202, 'spell' => 16164, 'ranks' => 1, 'tab' => 261],
                    ['node' => 4203, 'spell' => 16256, 'ranks' => 5, 'tab' => 263],
                ],
            ],
            PlayerClass::Mage => [
                'tree' => 1020, 'classId' => 8, 'spec' => 'Frost',
                'tabs' => [[301, 'Arcane'], [302, 'Fire'], [303, 'Frost']],
                'nodes' => [
                    ['node' => 5001, 'spell' => 11426, 'ranks' => 1, 'tab' => 303],
                    ['node' => 5002, 'spell' => 12472, 'ranks' => 1, 'tab' => 303],
                    ['node' => 5003, 'spell' => 12043, 'ranks' => 1, 'tab' => 301],
                ],
            ],
            PlayerClass::Warlock => [
                'tree' => 1009, 'classId' => 9, 'spec' => 'Affliction',
                'tabs' => [[201, 'Affliction'], [202, 'Demonology'], [203, 'Destruction']],
                'nodes' => [
                    ['node' => 6001, 'spell' => 18094, 'ranks' => 2, 'tab' => 201],
                    ['node' => 6002, 'spell' => 18265, 'ranks' => 1, 'tab' => 201],
                    ['node' => 6003, 'spell' => 17877, 'ranks' => 1, 'tab' => 203],
                ],
            ],
            PlayerClass::Druid => [
                'tree' => 1021, 'classId' => 11, 'spec' => 'Feral Combat',
                'tabs' => [[401, 'Balance'], [402, 'Feral Combat'], [403, 'Restoration']],
                'nodes' => [
                    ['node' => 7001, 'spell' => 16979, 'ranks' => 1, 'tab' => 402],
                    ['node' => 7002, 'spell' => 16864, 'ranks' => 1, 'tab' => 402],
                    ['node' => 7003, 'spell' => 17116, 'ranks' => 1, 'tab' => 401],
                ],
            ],
        };
    }

    /** The name of a talent the builds hold. */
    public static function talentName(int $spell): ?string
    {
        return [
            12294 => 'Mortal Strike', 12292 => 'Death Wish', 12317 => 'Enrage',
            20375 => 'Seal of Command', 20218 => 'Sanctity Aura', 20066 => 'Repentance',
            19574 => 'Bestial Wrath', 19577 => 'Intimidation', 19506 => 'Trueshot Aura',
            13877 => 'Blade Flurry', 13750 => 'Adrenaline Rush', 14251 => 'Riposte',
            14751 => 'Inner Focus', 20711 => 'Spirit of Redemption', 724 => 'Lightwell',
            17364 => 'Stormstrike', 16164 => 'Elemental Focus', 16256 => 'Flurry',
            11426 => 'Ice Barrier', 12472 => 'Icy Veins', 12043 => 'Presence of Mind',
            18094 => 'Nightfall', 18265 => 'Siphon Life', 17877 => 'Shadowburn',
            16979 => 'Feral Charge', 16864 => 'Omen of Clarity', 17116 => 'Nature\'s Swiftness',
        ][$spell] ?? null;
    }

    /** The item id of the weapon the class swings, for the main hand line; `World::ITEMS` names it. */
    public static function weapon(PlayerClass $class): int
    {
        return match ($class) {
            PlayerClass::Warrior => 12784,
            PlayerClass::Paladin => 1728,
            PlayerClass::Hunter => 2825,
            PlayerClass::Rogue => 2092,
            PlayerClass::Priest, PlayerClass::Shaman => 36,
            PlayerClass::Mage, PlayerClass::Warlock => 5210,
            PlayerClass::Druid => 35,
        };
    }
}
