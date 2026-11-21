<?php

namespace App\Support;

/** What a loot window opened on: the decoder's `loot_opens.source_kind` (docs/ROWS.md). */
enum LootSourceKind: string
{
    case Creature = 'c';
    case Object = 'g';
    case Bag = 'i';
    case Other = 'o';
}
