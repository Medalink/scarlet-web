<?php

namespace App\Support;

/**
 * How a name matched the text of a search, best first (docs/DESIGN.md, Search). The value is the name in the API and
 * in a hit's `match`. The order of the cases is the rank: a lower rank is a better match.
 */
enum SearchMatch: string
{
    case Exact = 'exact';
    case Prefix = 'prefix';
    case Word = 'word';
    case Contains = 'contains';
    case Fuzzy = 'fuzzy';

    /** The place of the tier in the order, 0 for the best. The SQL tier expression of `Search` returns the same numbers. */
    public function rank(): int
    {
        return match ($this) {
            self::Exact => 0,
            self::Prefix => 1,
            self::Word => 2,
            self::Contains => 3,
            self::Fuzzy => 4,
        };
    }

    public static function fromRank(int $rank): self
    {
        return match ($rank) {
            0 => self::Exact,
            1 => self::Prefix,
            2 => self::Word,
            4 => self::Fuzzy,
            default => self::Contains,
        };
    }

    /**
     * The words of a text: split on whitespace, lower case. Every one of them must be in a name for it to match.
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        return preg_split('/\s+/u', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * The tier of a name for a text, judged on the whole text first, then word by word; null when the name does not
     * match. `Fuzzy` is never returned: a close spelling is found by the caller when nothing else matched.
     */
    public static function judge(string $name, string $text): ?self
    {
        $words = self::words($text);
        if ($words === []) {
            return null;
        }
        $whole = implode(' ', $words);
        $name = mb_strtolower($name);
        if ($name === $whole) {
            return self::Exact;
        }
        if (str_starts_with($name, $whole)) {
            return self::Prefix;
        }
        if (self::startsAWord($name, $whole)) {
            return self::Word;
        }
        if (str_contains($name, $whole)) {
            return self::Contains;
        }
        if (count($words) < 2) {
            return null;
        }

        $tier = self::Word;
        foreach ($words as $word) {
            if (! str_contains($name, $word)) {
                return null;
            }
            $tier = self::startsAWord($name, $word) ? $tier : self::Contains;
        }

        return $tier;
    }

    private static function startsAWord(string $name, string $text): bool
    {
        return preg_match('/(?<![\p{L}\p{N}])'.preg_quote($text, '/').'/u', $name) === 1;
    }
}
