<?php

namespace App\Support;

/** The ids that fit the int4 columns the record pages read: the largest one, and the pattern that accepts no longer number. */
final class RecordId
{
    public const int MAX = 2147483647;

    /**
     * A non-capturing pattern for the digits of 0 to `MAX` with no sign, so a route or a rule turns a larger number away
     * (a 404, a validation error) before it reaches a column that cannot hold it.
     */
    public static function pattern(): string
    {
        $max = (string) self::MAX;
        $length = strlen($max);
        $branches = ['[0-9]{1,'.($length - 1).'}'];
        for ($place = 0; $place < $length; $place++) {
            $digit = (int) $max[$place];
            $lowest = $place === 0 ? 1 : 0;
            if ($digit <= $lowest) {
                continue;
            }
            $below = $lowest === $digit - 1 ? (string) $lowest : '['.$lowest.'-'.($digit - 1).']';
            $free = $length - $place - 1;
            $branches[] = substr($max, 0, $place).$below.match (true) {
                $free === 0 => '',
                $free === 1 => '[0-9]',
                default => '[0-9]{'.$free.'}',
            };
        }
        $branches[] = $max;

        return '(?:'.implode('|', $branches).')';
    }
}
