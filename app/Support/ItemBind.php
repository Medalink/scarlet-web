<?php

namespace App\Support;

/** How an item binds: `items.bind_type`. */
enum ItemBind: int
{
    case Pickup = 1;
    case Equip = 2;
    case Use = 3;
    case Quest = 4;
}
