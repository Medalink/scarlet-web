<?php

namespace App\Support;

/** How another member of the group came by an item: the decoder's `group_loot.kind` (docs/ROWS.md). */
enum GroupLootKind: string
{
    case Looted = 'l';
    case Pushed = 'p';
}
