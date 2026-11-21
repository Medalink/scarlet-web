<?php

namespace App\Support;

/** `talent_changes.kind`: what a `TA` line did to a node (docs/ROWS.md). */
enum TalentKind: string
{
    case Pick = 'pick';
    case Refund = 'refund';
    case Swap = 'swap';
}
