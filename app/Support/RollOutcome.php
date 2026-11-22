<?php

namespace App\Support;

/** How a roll ended for the player: the decoder's `loot_rolls.outcome` (docs/ROWS.md). `AllPassed` is a roll nobody took. */
enum RollOutcome: string
{
    case Won = 'won';
    case Lost = 'lost';
    case Passed = 'passed';
    case AllPassed = 'all_passed';
}
