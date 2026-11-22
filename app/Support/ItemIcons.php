<?php

namespace App\Support;

/** The item icons on the `maps` disk under `items/` (tools/scene-art/items.mjs), served at `/item-icons/<file id>.png`. */
final class ItemIcons
{
    public static function path(string $inside): string
    {
        return self::folder()->path($inside);
    }

    /**
     * @param  array<int, int>  $itemIds
     * @return array<int, string|null> the address of each item's icon, null when the art or the item has none
     */
    public static function icons(array $itemIds): array
    {
        return self::folder()->icons($itemIds);
    }

    private static function folder(): IconFolder
    {
        return new IconFolder('items', 'item-icons');
    }
}
