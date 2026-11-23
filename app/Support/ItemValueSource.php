<?php

namespace App\Support;

/** Where an item's value in a drop table comes from (docs/DESIGN.md, Imported market prices). */
enum ItemValueSource: string
{
    case Market = 'market';
    case Vendor = 'vendor';
}
