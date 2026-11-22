<?php

namespace App\Support\ClassRows;

/** The meters of `meter_totals` and `meter_spells` (docs/ROWS.md). */
enum Meter: string
{
    case Damage = 'd';
    case Healing = 'h';
    case Taken = 't';
    case Absorbs = 'a';
    case Interrupts = 'i';
    case Dispels = 'x';
}
