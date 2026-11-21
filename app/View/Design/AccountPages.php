<?php

namespace App\View\Design;

use App\View\Charts;

/** The data of docs/comps/account.html, login.html and link.html. */
final class AccountPages
{
    /**
     * @return array<string, mixed>
     */
    public static function data(string $page): array
    {
        return match ($page) {
            'account' => ['groups' => self::groups()],
            default => ['map' => Charts::map()],
        };
    }

    /**
     * Sharing groups as [name, what it covers, on, locked].
     *
     * @return list<list<mixed>>
     */
    private static function groups(): array
    {
        return [
            ['Character and sessions', 'Sessions, levels, the zones you enter, your ruleset. Needed by every chart, so it is always sent while uploading is on.', true, true],
            ['Experience and levelling', 'Experience gains and where they came from.', true, false],
            ['Quests', 'Accepts, turn-ins, objectives and rewards.', true, false],
            ['Combat, kills and deaths', 'Kills, deaths and what killed you, fights, bosses, casts.', true, false],
            ['PvP and duels', 'Honor, player kills, battlegrounds, duels.', true, false],
            ['Movement and map paths', 'Where you walked, flew and teleported.', true, false],
            ['Creatures and places seen', 'Creatures and objects you met and where you stood.', true, false],
            ['Loot, items and drop rates', 'What you looted and from what, loot rules and rolls.', true, false],
            ['Gold, vendors, auctions, mail and trade', 'Money, purchases, auctions, mail and trades.', false, false],
            ['Professions and crafting', 'Skills, crafts and recipes.', true, false],
            ['Character build and gear', 'Stats, gear, talents, spells and pets.', true, false],
            ['Social and the players you meet', "Groups, guild, friends and players seen. Other players' names are sent only with this on.", false, false],
            ['Collections, achievements, Legacy and reputation', 'Legacy Challenges and points, mounts, pets, reputation.', true, false],
            ['Game client and addons loaded', 'Client checks, the addon list, UI errors, screenshots taken.', true, false],
            ['Other addons', 'What Scarlet records from other addons you run.', true, false],
            ['Imported history from other addons', 'Gold, sales and purchases Scarlet read from other addons saved on this PC. Only you see them.', true, false],
        ];
    }
}
