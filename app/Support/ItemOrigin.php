<?php

namespace App\Support;

/** Where a received item came from, as far as the log says, best known first. */
enum ItemOrigin: int
{
    /** A loot window. */
    case Looted = 0;

    /** A quest turn-in within seconds of the receipt. */
    case Quest = 1;

    /** Nothing the log says: a purchase, mail, a trade or a group roll. */
    case Other = 2;

    public function label(): string
    {
        return match ($this) {
            self::Looted => 'looted',
            self::Quest => 'quest reward',
            self::Other => 'received',
        };
    }
}
