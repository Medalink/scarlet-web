<?php

namespace App\Support;

/**
 * What a player's name looks like, so a page never shows one it may not. WoW Forever names are `First-Last`; the app
 * replaces another player's name with a `~` pseudonym before upload (docs/DESIGN.md, Other players' names).
 */
final class PlayerNameShape
{
    public const string NAME = "/^[A-Z][a-z']+-[A-Z][a-z']+$/";

    public const string PSEUDONYM = '/^~[a-z2-7]{10}$/';

    public static function matches(string $text): bool
    {
        return preg_match(self::NAME, $text) === 1 || preg_match(self::PSEUDONYM, $text) === 1;
    }

    /** A `~` pseudonym the app wrote for another player's name. */
    public static function isPseudonym(string $text): bool
    {
        return preg_match(self::PSEUDONYM, $text) === 1;
    }
}
