<?php

namespace App\Support;

/**
 * The spell icons on the `maps` disk under `spells/` (tools/scene-art/spells.mjs), served at `/spell-icons/<file id>.png`.
 * They are kept apart from the item icons: a file id that is both a spell's and an item's icon is stored in each folder.
 */
final class SpellIcons
{
    public static function path(string $inside): string
    {
        return self::folder()->path($inside);
    }

    /**
     * @param  array<int, int>  $spellIds
     * @return array<int, string|null> the address of each spell's icon, null when the art or the spell has none
     */
    public static function icons(array $spellIds): array
    {
        return self::folder()->icons($spellIds);
    }

    public static function icon(int $spellId): ?string
    {
        return self::icons([$spellId])[$spellId];
    }

    private static function folder(): IconFolder
    {
        return new IconFolder('spells', 'spell-icons');
    }
}
