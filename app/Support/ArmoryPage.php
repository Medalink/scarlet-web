<?php

namespace App\Support;

/** The Forever Armory's character page, as far as the ruleset goes. */
final class ArmoryPage
{
    /** The page of a character by its lower-case name (`first-last`), in a region (`us`). */
    public static function url(string $region, string $nameKey): string
    {
        return 'https://worldofwarcraft.blizzard.com/en-us/forever/'.rawurlencode($region).'/armory/character/'.rawurlencode($nameKey);
    }

    /**
     * The ruleset the page names for the character, read from the profile the page embeds:
     * `"ruleset":{"name":"Normal","rulesetId":2,"slug":"normal"},"slug":"<character>"`. Null when the page has none,
     * or when it is the page of another character.
     *
     * @return array{name: string, id: int, slug: string}|null
     */
    public static function ruleset(string $html, string $nameKey): ?array
    {
        if (preg_match_all('/"ruleset":\{"name":"([^"]*)","rulesetId":(\d+),"slug":"([^"]*)"\},"slug":"([^"]*)"/', $html, $found, PREG_SET_ORDER) === 0) {
            return null;
        }
        foreach ($found as $match) {
            if (mb_strtolower(rawurldecode($match[4])) === mb_strtolower($nameKey)) {
                return ['name' => $match[1], 'id' => (int) $match[2], 'slug' => $match[3]];
            }
        }

        return null;
    }
}
