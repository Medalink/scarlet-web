<?php

namespace App\Support;

/** What dealt a killing blow: the decoder's `deaths.killer_kind`. */
enum KillerKind: string
{
    case Player = 'p';
    case Creature = 'c';
}
