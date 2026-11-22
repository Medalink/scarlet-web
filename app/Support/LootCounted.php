<?php

namespace App\Support;

/** The views that count a corpse once (docs/LOOT.md, Units of counting, rule 7). The value is the view name. */
enum LootCounted: string
{
    case Windows = 'loot_windows_counted';
    case Items = 'loot_items_counted';
    case WindowsCharacter = 'loot_windows_character_counted';
}
