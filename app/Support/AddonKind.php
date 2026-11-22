<?php

namespace App\Support;

/**
 * What an addon claim counts (`addon_claims.kind`), the kinds `scarlet-part decode --counts` accepts. Only a kind with
 * a source of Scarlet rows is reconciled against them; the others are held (docs/DESIGN.md, Reconcile).
 */
enum AddonKind: string
{
    case Fishing = 'fishing';
    case Kill = 'kill';
    case Run = 'run';
    case Xp = 'xp';

    public function hasRows(): bool
    {
        return $this === self::Fishing;
    }
}
