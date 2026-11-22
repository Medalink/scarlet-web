<?php

namespace App\Support;

/** The role a talent tree plays in a group: the scope of the role feats. */
enum Role: string
{
    case Healer = 'healer';
    case Tank = 'tank';
    case Melee = 'melee';
    case Ranged = 'ranged';
}
