<?php

namespace App\Support;

/** The tables of kills joined to their corpse by key (docs/LOOT.md, Units of counting, rule 7). The value is the table name. */
enum KeyedKills: string
{
    case Kills = 'loot_kills_keyed_daily';
    case Items = 'loot_kill_items_keyed_daily';
}
