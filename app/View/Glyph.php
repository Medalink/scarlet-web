<?php

namespace App\View;

/** The one table from what a figure counts to its glyph in `<x-icon>`; the first needle found in the label wins, so a longer phrase stands before the word inside it. */
final class Glyph
{
    private const array RULES = [
        ['characters killed', 'deaths'],
        ['killer level', 'level'],
        ['death', 'deaths'],
        ['skill', 'skills'],
        ['kill', 'kills'],
        ['hour', 'hours'],
        ['played', 'hours'],
        ['time', 'hours'],
        ['turn-in', 'quests'],
        ['quest', 'quests'],
        ['dungeon', 'dungeons'],
        ['gold', 'gold'],
        ['price', 'gold'],
        ['received', 'gold'],
        ['paid', 'gold'],
        ['level', 'level'],
        ['zone', 'zones'],
        ['width', 'distance'],
        ['distance', 'distance'],
        ['character', 'characters'],
        ['creature', 'creature'],
        ['drop', 'item'],
        ['loot', 'item'],
        ['item', 'item'],
        ['fish', 'fish'],
    ];

    /** The glyph name for a figure's label, or null when none fits. */
    public static function for(string $label): ?string
    {
        foreach (self::RULES as [$needle, $glyph]) {
            if (stripos($label, $needle) !== false) {
                return $glyph;
            }
        }

        return null;
    }
}
