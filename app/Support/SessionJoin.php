<?php

namespace App\Support;

/**
 * How a session follows the character's session before it (`sessions.joins`). Only `Continuous` and `First` let a level
 * that spans the boundary count as complete: `Gap` means play happened that Scarlet did not record, `Unknown` that
 * neither the clock nor the client's played counter can rule it out.
 */
enum SessionJoin: string
{
    case First = 'first';
    case Continuous = 'continuous';
    case Gap = 'gap';
    case Unknown = 'unknown';

    /** The values of the joins that break a level's interval, for a query's `whereIn`. */
    public const array BROKEN = ['gap', 'unknown'];
}
