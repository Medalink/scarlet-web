<?php

namespace App\Support;

/** How `/loot` orders its table (`?sort=`): by windows, by distinct items, or by level, lowest first. */
enum LootSort: string
{
    case Windows = 'windows';
    case Items = 'items';
    case Level = 'level';
}
