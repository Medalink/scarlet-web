<?php

namespace App\Support\ClassRows;

/** Where an item flow happened: the `place` of an `item_flows` row (docs/ROWS.md, `item_flows`). */
enum FlowPlace: string
{
    case Vendor = 'v';
    case Bank = 'b';
    case Mail = 'm';
    case Trade = 't';
    case Loot = 'l';
    case Auction = 'a';
    case Profession = 'c';
    case GuildBank = 'g';
    case Other = 'o';
    case Destroyed = 'x';
}
