<?php

namespace App\Support;

/** What a chart of arms splits one source's windows by: the loot rule in force, or the group size inside one rule. */
enum ArmSplit: string
{
    case Rule = 'rule';
    case Size = 'size';
}
