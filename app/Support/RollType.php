<?php

namespace App\Support;

/** What a player chose on a roll: the decoder's `loot_rolls.roll_type` (docs/ROWS.md). */
enum RollType: string
{
    case Pass = 'pass';
    case Need = 'need';
    case Greed = 'greed';
    case Disenchant = 'disenchant';
    case Transmog = 'transmog';
}
