<?php

namespace App\Support;

/** What a sharing group holds, in the words of the account page (docs/DESIGN.md, Sharing groups). */
final class GroupDescriptions
{
    private const array TEXT = [
        'core' => 'Sessions, levels, the zones you enter and your ruleset. Needed by every chart, so it is always sent while uploading is on.',
        'xp' => 'Experience gains and where they came from.',
        'quests' => 'Accepts, turn-ins, objectives and rewards.',
        'combat' => 'Kills, deaths, what killed you, fights, bosses and casts.',
        'pvp' => 'Honor, player kills, battlegrounds and duels.',
        'movement' => 'Where you walked, flew and teleported.',
        'world' => 'Creatures and objects you met and where you stood.',
        'loot' => 'What you looted and from what, loot rules and rolls.',
        'economy' => 'Money, purchases, auctions, mail and trades.',
        'professions' => 'Skills, crafts and recipes.',
        'build' => 'Stats, gear, talents, spells and pets.',
        'social' => "Groups, guild, friends and players seen. Other players' names are sent only with this on.",
        'collections' => 'Legacy Challenges and points, mounts, pets and reputation.',
        'client' => 'Client checks, the addon list, UI errors and screenshots taken.',
        'addons' => 'What Scarlet records from other addons you run.',
        'fishing' => 'Records, journal, fishing sessions, bounties and the spot leaderboards Scarlet read from The Fishing Log. Other anglers are sent as pseudonyms.',
        'counts' => 'Casts, catches, kills and runs that other addons on your PC counted, added to your totals once.',
        'imports' => 'Gold, sales and purchases Scarlet read from other addons on your PC. Only you see them.',
    ];

    public static function of(string $group): string
    {
        return self::TEXT[$group] ?? '';
    }
}
