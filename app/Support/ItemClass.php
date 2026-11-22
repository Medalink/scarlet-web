<?php

namespace App\Support;

/** The item classes pages pick on: `items.class_id`. */
enum ItemClass: int
{
    case Weapon = 2;
    case Armor = 4;
    case TradeGoods = 7;

    /** The trade goods subclass of leather and hide. Skinning gives it. */
    public const int LEATHER = 6;
}
