<?php

namespace App\Support;

/**
 * What reconcile decided about an addon claim (docs/DESIGN.md, Addon counts, Reconcile). The words are the ones
 * `scarlet-part reconcile` writes and `addon_claims.status` stores.
 */
enum ClaimStatus: string
{
    case Counted = 'counted';
    case Residual = 'residual';
    case Superseded = 'superseded';
    case Held = 'held';
    case Rejected = 'rejected';
    case Reference = 'reference';

    /** @return list<string> the statuses whose counted amounts are always 0 */
    public static function uncounted(): array
    {
        return [self::Superseded->value, self::Held->value, self::Rejected->value, self::Reference->value];
    }

    /** @return list<string> the statuses that add to a character's totals */
    public static function adding(): array
    {
        return [self::Counted->value, self::Residual->value];
    }
}
