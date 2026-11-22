<?php

namespace App\View;

use App\Support\SearchMatch;

/** A title cut into the pieces a search marks: every word of the text, ignoring case. */
final class Marks
{
    /**
     * @return list<array{0: string, 1: bool}> the pieces in order, true for a matched one; the title whole when no word is in it
     */
    public static function cut(string $title, string $text): array
    {
        $flags = [];
        foreach (SearchMatch::words($text) as $word) {
            $length = mb_strlen($word);
            for ($at = mb_stripos($title, $word); $at !== false; $at = mb_stripos($title, $word, $at + $length)) {
                for ($i = $at; $i < $at + $length; $i++) {
                    $flags[$i] = true;
                }
            }
        }
        if ($flags === []) {
            return [[$title, false]];
        }

        $pieces = [];
        $start = 0;
        $size = mb_strlen($title);
        for ($i = 1; $i <= $size; $i++) {
            if ($i < $size && isset($flags[$i]) === isset($flags[$i - 1])) {
                continue;
            }
            $pieces[] = [mb_substr($title, $start, $i - $start), isset($flags[$start])];
            $start = $i;
        }

        return $pieces;
    }

    /** @return list<array{0: string, 1: bool}> */
    public static function plain(string $title): array
    {
        return [[$title, false]];
    }
}
