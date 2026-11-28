<?php

namespace App\View;

/** Item class, subclass and equip slot ids as the words a player uses: "mail feet", "two-handed axe". */
final class ItemTypes
{
    public const int WEAPON = 2;

    public const int FISHING_POLE = 20;

    private const array CLASSES = [
        0 => 'consumable', 1 => 'container', 2 => 'weapon', 3 => 'gem', 4 => 'armor', 5 => 'reagent', 6 => 'projectile', 7 => 'trade good',
        9 => 'recipe', 11 => 'quiver', 12 => 'quest item', 13 => 'key', 15 => 'miscellaneous item',
    ];

    private const array ARMOR = [0 => 'miscellaneous', 1 => 'cloth', 2 => 'leather', 3 => 'mail', 4 => 'plate', 6 => 'shield'];

    private const array WEAPONS = [
        0 => 'one-handed axe', 1 => 'two-handed axe', 2 => 'bow', 3 => 'gun', 4 => 'one-handed mace', 5 => 'two-handed mace', 6 => 'polearm',
        7 => 'one-handed sword', 8 => 'two-handed sword', 10 => 'staff', 13 => 'fist weapon', 14 => 'miscellaneous weapon', 15 => 'dagger',
        16 => 'thrown weapon', 18 => 'crossbow', 19 => 'wand', 20 => 'fishing pole',
    ];

    private const array SLOTS = [
        'INVTYPE_HEAD' => 'head', 'INVTYPE_NECK' => 'neck', 'INVTYPE_SHOULDER' => 'shoulders', 'INVTYPE_BODY' => 'shirt', 'INVTYPE_CHEST' => 'chest',
        'INVTYPE_ROBE' => 'chest', 'INVTYPE_WAIST' => 'waist', 'INVTYPE_LEGS' => 'legs', 'INVTYPE_FEET' => 'feet', 'INVTYPE_WRIST' => 'wrists',
        'INVTYPE_HAND' => 'hands', 'INVTYPE_FINGER' => 'finger', 'INVTYPE_TRINKET' => 'trinket', 'INVTYPE_CLOAK' => 'back',
        'INVTYPE_SHIELD' => 'off hand', 'INVTYPE_HOLDABLE' => 'off hand', 'INVTYPE_RELIC' => 'relic', 'INVTYPE_TABARD' => 'tabard',
    ];

    /** "mail feet", "two-handed axe", "quest item". */
    public static function describe(?int $class, ?int $subclass, ?string $slot): string
    {
        if ($class === 4) {
            $material = self::ARMOR[$subclass ?? 0] ?? 'armor';
            $where = self::SLOTS[$slot ?? ''] ?? null;

            return $where === null || $subclass === 6 ? $material : ($subclass === 0 ? $where : $material.' '.$where);
        }
        if ($class === 2) {
            return self::WEAPONS[$subclass ?? 0] ?? 'weapon';
        }

        return self::CLASSES[$class ?? -1] ?? 'item';
    }

    /** The colour class of an item's name: 0 poor to 5 legendary. Common items have none. */
    public static function quality(?int $quality): ?string
    {
        return $quality === null || $quality === 1 ? null : 'q'.$quality;
    }
}
