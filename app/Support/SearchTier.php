<?php

namespace App\Support;

/**
 * The tiers of `SearchMatch` as SQL, written once for every kind: the condition that a name holds every word of the
 * text, and the rank of the name (0 exact to 3 contains, the numbers of `SearchMatch::rank()`). Columns are named by
 * the callers in code; every value is a binding.
 */
final class SearchTier
{
    /**
     * Every word of the text is somewhere in the column, or the id is the number the text is.
     *
     * @param  literal-string  $column
     * @param  literal-string|null  $idColumn
     * @return array{0: literal-string, 1: list<string|int>} the SQL and its bindings
     */
    public static function filter(string $column, string $text, ?string $idColumn = null): array
    {
        $words = SearchMatch::words($text);
        $holds = implode(' and ', array_fill(0, count($words), $column.' ilike ?'));
        $bindings = array_map(fn (string $word): string => '%'.self::like($word).'%', $words);
        $id = self::id($text);
        if ($idColumn === null || $id === null) {
            return ['('.$holds.')', $bindings];
        }

        return ['(('.$holds.') or '.$idColumn.' = ?)', [...$bindings, $id]];
    }

    /**
     * The rank of the column for the text as a select expression named `tier`: judged on the whole text first, then
     * word by word, as `SearchMatch::judge()` does. A name whose id is the number the text is ranks exact. `$grouped`
     * takes the best rank of the rows of one id.
     *
     * @param  literal-string  $column
     * @param  literal-string|null  $idColumn
     * @return array{0: literal-string, 1: list<string|int>} the SQL and its bindings
     */
    public static function rank(string $column, string $text, ?string $idColumn = null, bool $grouped = false): array
    {
        $words = SearchMatch::words($text);
        $whole = implode(' ', $words);
        $sql = 'case when lower('.$column.') = ? then 0 when '.$column.' ilike ? then 1 when '.$column.' ~* ? then 2 when '.$column.' ilike ? then 3';
        $bindings = [$whole, self::like($whole).'%', self::startsWord($whole), '%'.self::like($whole).'%'];
        if (count($words) > 1) {
            $each = implode(' and ', array_fill(0, count($words), $column.' ~* ?'));
            $sql .= ' when '.$each.' then 2';
            array_push($bindings, ...array_map(self::startsWord(...), $words));
        }
        $sql .= ' else 3 end';
        $id = self::id($text);
        if ($idColumn !== null && $id !== null) {
            $sql = 'case when '.$idColumn.' = ? then 0 else '.$sql.' end';
            array_unshift($bindings, $id);
        }

        return [$grouped ? 'min('.$sql.') as tier' : $sql.' as tier', $bindings];
    }

    /** The number a text of digits only is, or null. */
    public static function id(string $text): ?int
    {
        return preg_match('/^\d{1,9}$/', trim($text)) === 1 ? (int) trim($text) : null;
    }

    private static function like(string $text): string
    {
        return addcslashes($text, '\\%_');
    }

    private static function startsWord(string $text): string
    {
        return '(^|[^[:alnum:]])'.preg_quote($text, '/');
    }
}
