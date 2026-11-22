<?php

namespace App\Support;

/** The armour types a class wears or does not: `items.subclass_id` of an armour item (docs/ROWS.md). Jewellery and cloaks (subclass 0) have none. */
enum ArmorType: int
{
    case Cloth = 1;
    case Leather = 2;
    case Mail = 3;
    case Plate = 4;
    case Shield = 6;

    /** @return list<int> */
    public static function ids(): array
    {
        return array_map(fn (self $type): int => $type->value, self::cases());
    }
}
