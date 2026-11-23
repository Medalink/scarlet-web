<?php

namespace Database\Seeders\Population;

use App\Models\RulesetKind;
use App\Support\PlayerClass;

/**
 * The world the recordings play in: zones by their uiMapIDs (docs/map-rects.tsv) with the levels they suit,
 * dungeons by instance map id, the races each class may be, and the goods, creatures and quests the sessions
 * pick up. The site learns every name from the recordings, so these only have to agree with one another.
 */
final class World
{
    public const int HORDE = 1;

    public const int ALLIANCE = 2;

    /** uiMapID => [name, lowest level, highest level, side: `A`, `H` or `*`]. */
    public const array ZONES = [
        1429 => ['Elwynn Forest', 1, 10, 'A'], 1426 => ['Dun Morogh', 1, 10, 'A'], 1438 => ['Teldrassil', 1, 10, 'A'],
        1411 => ['Durotar', 1, 10, 'H'], 1412 => ['Mulgore', 1, 10, 'H'], 1420 => ['Tirisfal Glades', 1, 10, 'H'],
        1436 => ['Westfall', 10, 20, 'A'], 1432 => ['Loch Modan', 10, 20, 'A'], 1439 => ['Darkshore', 10, 20, 'A'],
        1421 => ['Silverpine Forest', 10, 20, 'H'], 1413 => ['The Barrens', 10, 30, 'H'],
        1433 => ['Redridge Mountains', 15, 25, 'A'], 1431 => ['Duskwood', 18, 30, 'A'], 1437 => ['Wetlands', 20, 30, 'A'],
        1440 => ['Ashenvale', 18, 30, '*'], 1424 => ['Hillsbrad Foothills', 20, 30, '*'],
        1442 => ['Stonetalon Mountains', 15, 27, '*'], 1441 => ['Thousand Needles', 25, 35, '*'],
        1434 => ['Stranglethorn Vale', 30, 45, '*'], 1417 => ['Arathi Highlands', 30, 40, '*'],
        1416 => ['Alterac Mountains', 30, 40, '*'], 1443 => ['Desolace', 30, 40, '*'],
        1445 => ['Dustwallow Marsh', 35, 45, '*'], 1435 => ['Swamp of Sorrows', 35, 45, '*'],
        1418 => ['Badlands', 35, 45, '*'], 1446 => ['Tanaris', 40, 50, '*'], 1444 => ['Feralas', 40, 50, '*'],
        1425 => ['The Hinterlands', 42, 52, '*'], 1427 => ['Searing Gorge', 43, 50, '*'],
        1419 => ['Blasted Lands', 45, 55, '*'], 1449 => ['Un\'Goro Crater', 48, 55, '*'],
        1448 => ['Felwood', 48, 55, '*'], 1428 => ['Burning Steppes', 50, 58, '*'],
        1422 => ['Western Plaguelands', 51, 58, '*'], 1423 => ['Eastern Plaguelands', 53, 60, '*'],
        1452 => ['Winterspring', 53, 60, '*'], 1451 => ['Silithus', 55, 60, '*'],
    ];

    /** uiMapID => name, the capital a character rests in. */
    public const array CITIES = [1453 => 'Stormwind City', 1455 => 'Ironforge', 1454 => 'Orgrimmar', 1458 => 'Undercity'];

    /** instance map id => [name, lowest level, bosses]. */
    public const array DUNGEONS = [
        36 => ['The Deadmines', 15, ['Rhahk\'Zor', 'Sneed\'s Shredder', 'Gilnid', 'Mr. Smite', 'Captain Greenskin', 'Edwin VanCleef']],
        33 => ['Shadowfang Keep', 18, ['Rethilgore', 'Baron Silverlaine', 'Commander Springvale', 'Archmage Arugal']],
        43 => ['Wailing Caverns', 17, ['Lord Cobrahn', 'Lady Anacondra', 'Lord Pythas', 'Mutanus the Devourer']],
        47 => ['Razorfen Kraul', 30, ['Aggem Thorncurse', 'Death Speaker Jargba', 'Overlord Ramtusk', 'Charlga Razorflank']],
        90 => ['Gnomeregan', 29, ['Grubbis', 'Viscous Fallout', 'Electrocutioner 6000', 'Mekgineer Thermaplugg']],
        209 => ['Zul\'Farrak', 44, ['Antu\'sul', 'Witch Doctor Zum\'rah', 'Chief Ukorz Sandscalp']],
        129 => ['Razorfen Downs', 37, ['Tuten\'kash', 'Mordresh Fire Eye', 'Amnennar the Coldbringer']],
        189 => ['Scarlet Monastery', 34, ['Interrogator Vishas', 'Houndmaster Loksey', 'Herod', 'High Inquisitor Whitemane']],
        70 => ['Uldaman', 41, ['Revelosh', 'Ironaya', 'Archaedas']],
        349 => ['Maraudon', 46, ['Noxxion', 'Princess Theradras']],
        329 => ['Stratholme', 58, ['Timmy the Cruel', 'Baroness Anastari', 'Baron Rivendare']],
        289 => ['Scholomance', 58, ['Rattlegore', 'Jandice Barov', 'Darkmaster Gandling']],
        230 => ['Blackrock Depths', 52, ['High Interrogator Gerstahn', 'Emperor Dagran Thaurissan']],
    ];

    /** class => races as the client names them. */
    public const array RACES = [
        'WARRIOR' => ['Human', 'Dwarf', 'Night Elf', 'Gnome', 'Orc', 'Undead', 'Tauren', 'Troll'],
        'PALADIN' => ['Human', 'Dwarf'],
        'HUNTER' => ['Dwarf', 'Night Elf', 'Orc', 'Tauren', 'Troll'],
        'ROGUE' => ['Human', 'Dwarf', 'Night Elf', 'Gnome', 'Orc', 'Undead', 'Troll'],
        'PRIEST' => ['Human', 'Dwarf', 'Night Elf', 'Undead', 'Troll'],
        'SHAMAN' => ['Orc', 'Tauren', 'Troll'],
        'MAGE' => ['Human', 'Gnome', 'Undead', 'Troll'],
        'WARLOCK' => ['Human', 'Gnome', 'Orc', 'Undead'],
        'DRUID' => ['Night Elf', 'Tauren'],
    ];

    public const array HORDE_RACES = ['Orc', 'Undead', 'Tauren', 'Troll'];

    /** race => the zone a new character starts in. */
    public const array HOME_ZONE = [
        'Human' => 1429, 'Dwarf' => 1426, 'Gnome' => 1426, 'Night Elf' => 1438,
        'Orc' => 1411, 'Troll' => 1411, 'Tauren' => 1412, 'Undead' => 1420,
    ];

    /** ruleset kind => the realm ids the characters of that kind live on; the two Normal realms come with the migrations. */
    public const array REALMS = [
        'normal' => [6794, 6931],
        'pvp' => [6502],
        'roleplay' => [6604],
        'roleplay-pvp' => [6706],
    ];

    public const array REALM_NAMES = [
        6502 => 'Local PvP Realm 1', 6604 => 'Local RP Realm 1', 6706 => 'Local RP PvP Realm 1',
    ];

    /** item id => name, the goods the recordings pick up, craft and spend. */
    public const array ITEMS = [
        6291 => 'Raw Brilliant Smallfish', 6303 => 'Raw Slitherskin Mackerel', 7973 => 'Big-mouth Clam',
        7971 => 'Black Pearl', 6256 => 'Fishing Pole', 2770 => 'Copper Ore', 2771 => 'Tin Ore',
        3858 => 'Mithril Ore', 2447 => 'Peacebloom', 765 => 'Silverleaf', 785 => 'Mageroyal',
        3355 => 'Wild Steelbloom', 3356 => 'Kingsblood', 2934 => 'Ruined Leather Scraps', 2318 => 'Light Leather',
        2319 => 'Medium Leather', 4234 => 'Heavy Leather', 2679 => 'Charred Wolf Meat',
        2862 => 'Rough Sharpening Stone', 2589 => 'Linen Cloth', 2996 => 'Bolt of Linen Cloth',
        2592 => 'Wool Cloth', 4306 => 'Silk Cloth', 2840 => 'Copper Bar', 3576 => 'Tin Bar', 3575 => 'Iron Bar',
        858 => 'Lesser Healing Potion', 118 => 'Minor Healing Potion', 4540 => 'Tough Hunk of Bread',
        4541 => 'Freshly Baked Bread', 117 => 'Tough Jerky', 159 => 'Refreshing Spring Water', 1205 => 'Melon Juice',
        2455 => 'Minor Mana Potion', 5503 => 'Clam Meat', 5504 => 'Tangy Clam Meat', 4338 => 'Mageweave Cloth',
        3182 => 'Spider\'s Silk', 2672 => 'Stringy Wolf Meat', 769 => 'Chunk of Boar Meat', 723 => 'Goretusk Liver',
        1529 => 'Jade', 1210 => 'Shadowgem', 3864 => 'Citrine', 1705 => 'Lesser Moonstone', 1206 => 'Moss Agate',
        2841 => 'Bronze Bar', 3577 => 'Gold Bar', 3860 => 'Mithril Bar', 2776 => 'Gold Ore', 2835 => 'Rough Stone',
        2836 => 'Coarse Stone', 2838 => 'Heavy Stone', 12784 => 'Arcanite Reaper', 1728 => 'Teebu\'s Blazing Longsword',
        2825 => 'Bow of Searing Arrows', 2092 => 'Worn Dagger', 36 => 'Worn Mace', 5210 => 'Burning Wand',
        35 => 'Bent Staff',
    ];

    /** The herbs, veins and animals the professions cast on, with the id their loot windows carry. */
    public const array NODES = [
        'mine' => [
            ['spell' => 2575, 'node' => 1731, 'item' => 2770],
            ['spell' => 2575, 'node' => 1732, 'item' => 2771],
            ['spell' => 2575, 'node' => 2040, 'item' => 3858],
        ],
        'herb' => [
            ['spell' => 2366, 'node' => 1617, 'item' => 2447],
            ['spell' => 2366, 'node' => 1618, 'item' => 765],
            ['spell' => 2366, 'node' => 1619, 'item' => 785],
            ['spell' => 2366, 'node' => 1620, 'item' => 3355],
        ],
        'skin' => [
            ['spell' => 8613, 'creature' => 113, 'item' => 2934],
            ['spell' => 8613, 'creature' => 3098, 'item' => 2318],
            ['spell' => 8613, 'creature' => 1128, 'item' => 2319],
        ],
    ];

    /** creature id => [name, lowest level, highest level]: what a character of that level fights. */
    public const array PREY = [
        299 => ['Diseased Young Wolf', 1, 6], 113 => ['Stonetusk Boar', 1, 8], 80 => ['Kobold Laborer', 1, 8],
        157 => ['Goretusk', 4, 12], 94 => ['Defias Cutpurse', 8, 16], 589 => ['Defias Pillager', 10, 18],
        1128 => ['Young Black Bear', 10, 20], 3098 => ['Mottled Boar', 10, 20], 1130 => ['Bloodsail Sailor', 15, 24],
        1111 => ['Mountain Cougar', 16, 26], 1152 => ['Snapjaw Crocolisk', 20, 32],
        1085 => ['Elder Stranglethorn Tiger', 28, 40], 683 => ['Young Panther', 28, 40], 682 => ['Stranglethorn Tiger', 30, 42],
        687 => ['Jungle Stalker', 32, 44], 2504 => ['Boulderfist Ogre', 36, 46],
        2565 => ['Giant Surf Glider', 38, 48], 5429 => ['Quillfang Ravager', 44, 54], 6502 => ['Un\'Goro Stomper', 48, 58],
        7123 => ['Ashenvale Treant', 52, 60], 8281 => ['Felwood Corruptor', 54, 60], 8955 => ['Bile Spewer', 56, 60],
    ];

    /** quest id => [lowest level, xp reward, copper reward]. */
    public const array QUESTS = [
        783 => [1, 170, 90], 33 => [2, 250, 120], 7 => [3, 350, 160], 54 => [5, 450, 210], 9 => [6, 550, 260],
        176 => [10, 950, 400], 65 => [12, 1100, 500], 239 => [15, 1400, 680], 247 => [18, 1700, 900],
        1275 => [22, 2200, 1200], 1131 => [25, 2500, 1500], 1204 => [30, 3300, 2100], 1124 => [35, 4100, 3000],
        1366 => [40, 5000, 4500], 2603 => [45, 5900, 6400], 2929 => [50, 7000, 8800], 3792 => [55, 8200, 11000],
    ];

    /**
     * @return list<string> the races a class may be, for one side or both
     */
    public static function racesOf(PlayerClass $class, ?int $faction = null): array
    {
        $races = self::RACES[$class->value];

        if ($faction === null) {
            return $races;
        }

        return array_values(array_filter(
            $races,
            fn (string $race): bool => in_array($race, self::HORDE_RACES, true) === ($faction === self::HORDE),
        ));
    }

    public static function factionOf(string $race): int
    {
        return in_array($race, self::HORDE_RACES, true) ? self::HORDE : self::ALLIANCE;
    }

    public static function realmFor(RulesetKind $kind, Rng $rng): int
    {
        return $rng->pick(self::REALMS[$kind->value] ?? self::REALMS['normal']);
    }

    /**
     * The zones a character of a level and side hunts in.
     *
     * @return list<int>
     */
    public static function zonesFor(int $level, int $faction): array
    {
        $side = $faction === self::HORDE ? 'H' : 'A';
        $zones = [];

        foreach (self::ZONES as $id => [, $low, $high, $owner]) {
            if ($level >= $low && $level <= $high && ($owner === '*' || $owner === $side)) {
                $zones[] = $id;
            }
        }

        return $zones !== [] ? $zones : [array_key_first(self::ZONES)];
    }

    /**
     * The creatures a character of a level fights.
     *
     * @return list<int>
     */
    public static function preyFor(int $level): array
    {
        $prey = [];

        foreach (self::PREY as $id => [, $low, $high]) {
            if ($level >= $low && $level <= $high) {
                $prey[] = $id;
            }
        }

        return $prey !== [] ? $prey : [113];
    }

    public static function zoneName(int $zoneId): string
    {
        return self::ZONES[$zoneId][0] ?? self::CITIES[$zoneId] ?? 'Unknown Zone';
    }

    /**
     * A spot inside a zone, away from the edges of its map.
     *
     * @return array{int, int}
     */
    public static function spot(string $key, int $zoneId): array
    {
        $rng = Rng::for('spot:'.$key.':'.$zoneId);

        return [$rng->interval(2800, 7200), $rng->interval(2800, 7200)];
    }
}
