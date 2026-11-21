<?php

namespace App\Support;

/** Where an experience gain came from: the decoder's `xp_gains.source`. */
enum XpSource: string
{
    case Kill = 'k';
    case Dungeon = 'd';
    case Quest = 'q';
    case Exploring = 'e';
    case Other = 'o';

    public function label(): string
    {
        return match ($this) {
            self::Kill => 'kills',
            self::Dungeon => 'dungeon kills',
            self::Quest => 'quests',
            self::Exploring => 'exploring',
            self::Other => 'other',
        };
    }
}
