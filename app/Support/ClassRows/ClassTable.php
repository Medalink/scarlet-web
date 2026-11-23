<?php

namespace App\Support\ClassRows;

/** The six class tables (docs/ROWS.md, "The class tables"). The value is the table's name. */
enum ClassTable: string
{
    case Casts = 'spell_casts';
    case Meters = 'meter_totals';
    case MeterSpells = 'meter_spells';
    case States = 'state_times';
    case Items = 'item_flows';
    case Tallies = 'class_tallies';
}
