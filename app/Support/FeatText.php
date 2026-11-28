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

    /** The web pages' amount: `amount`, and "under 0.1" for a count that would read 0.0. The upload API keeps `amount`. */
    public static function readable(Feat $feat, int|float $n): string
    {
        $shown = self::unitValue($feat, $n);

        return $shown > 0 && $shown < 0.1 ? 'under 0.1' : self::amount($feat, $n);
    }

    /** The web pages' `counted`: "under 0.1 miles". */
    public static function readableCounted(Feat $feat, int|float $value): string
    {
        return self::readable($feat, $value).' '.self::noun($feat, $value);
    }

    /** The web pages' `toNext`: "under 0.1 to Epic". */
    public static function readableToNext(Feat $feat, int|float $left, int $tier): ?string
    {
        $next = Feat::TIERS[$tier] ?? null;

        return $next === null ? null : self::readable($feat, $left).' to '.$next;
    }

    /**
     * A feat card's `counted` and `to_next` in the web pages' words: the API's leading amount becomes `readable`'s, and
     * what the API appends (" on 3 characters", " · Held by …") stays.
     *
     * @param  array<string, mixed>  $card
     * @return array{counted: mixed, to_next: mixed}
     */
    public static function readableCard(Feat $feat, array $card): array
    {
        $value = $card['value'] ?? null;
        $left = $card['left'] ?? null;
        $tier = is_int($card['tier'] ?? null) ? $card['tier'] : 0;
        $counted = $card['counted'] ?? null;
        $toNext = $card['to_next'] ?? null;

        return [
            'counted' => is_string($counted) && (is_int($value) || is_float($value)) ? self::swap($counted, self::counted($feat, $value), self::readableCounted($feat, $value)) : $counted,
            'to_next' => is_string($toNext) && (is_int($left) || is_float($left)) ? self::swap($toNext, (string) self::toNext($feat, $left, $tier), (string) self::readableToNext($feat, $left, $tier)) : $toNext,
        ];
    }

    private static function swap(string $text, string $from, string $to): string
    {
        return $from !== '' && str_starts_with($text, $from) ? $to.substr($text, strlen($from)) : $text;
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
