<?php

namespace App\Support\ClassRows;

/** Where a fight a damage meter reported took place: the `place` of a `meter_totals` row (docs/ROWS.md). */
enum MeterPlace: string
{
    case World = 'w';
    case Dungeon = 'd';
    case Raid = 'r';
    case Battleground = 'b';
}
