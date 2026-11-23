<?php

namespace App\Support;

use App\View\Format;

/** A feat's count in words the way the app's featcard.js says it: miles for a feat counted in yards, one decimal under 10. */
final class FeatText
{
    /** A count in the unit of the page: yards of a mile feat become miles. */
    public static function amount(Feat $feat, int|float $n): string
    {
        $shown = self::unitValue($feat, $n);

        return floor($shown) == $shown || $shown >= 10 ? Format::number($shown) : Format::number($shown, 1);
    }

    /** "9,912 kills", from the catalog's words for one and many. */
    public static function counted(Feat $feat, int|float $value): string
    {
        return self::amount($feat, $value).' '.self::noun($feat, $value);
    }

    /** What a count is of: the catalog's word for one or for many. */
    public static function noun(Feat $feat, int|float $value): string
    {
        $row = FeatRows::row($feat);

        return self::unitValue($feat, $value) == 1 ? $row[1] : $row[2];
    }

    /** "88 to Epic": what is left to the tier above `$tier`, null at Scarlet. */
    public static function toNext(Feat $feat, int|float $left, int $tier): ?string
    {
        $next = Feat::TIERS[$tier] ?? null;

        return $next === null ? null : self::amount($feat, $left).' to '.$next;
    }

    private static function unitValue(Feat $feat, int|float $n): int|float
    {
        return $feat->unit() === null ? $n : $n / 1760;
    }
}
