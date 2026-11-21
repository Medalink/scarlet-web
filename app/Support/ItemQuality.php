<?php

namespace App\Support;

/** An item's quality: `items.quality`. Common is white, so it has no name colour; `c-q<n>` fills a bar, `q<n>` colours a name. */
enum ItemQuality: int
{
    case Poor = 0;
    case Common = 1;
    case Uncommon = 2;
    case Rare = 3;
    case Epic = 4;

    public function label(): string
    {
        return match ($this) {
            self::Poor => 'Poor',
            self::Common => 'Common',
            self::Uncommon => 'Uncommon',
            self::Rare => 'Rare',
            self::Epic => 'Epic',
        };
    }

    /** The class that fills a bar or a key swatch. */
    public function fill(): string
    {
        return 'c-q'.$this->value;
    }
}
