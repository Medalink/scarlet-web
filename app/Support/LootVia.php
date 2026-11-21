<?php

namespace App\Support;

/** How a loot row was seen: the decoder's `loot_drops.via` (docs/ROWS.md). */
enum LootVia: string
{
    /** An item in a loot window. */
    case Window = 'window';

    /** An item-received line; its source is the last loot window within 5 seconds, else null. */
    case Receipt = 'receipt';
}
