<?php

namespace App\Support\ClassRows;

/** The `class_tallies` rows of a character, summed by key and by what the key is about (docs/ROWS.md, `class_tallies`). */
final class Tallies
{
    /** @var array<string, list<TallyRow>> */
    private array $byKey = [];

    /** @param  list<\stdClass>  $rows  columns key, a, b, n, sum, best */
    public function __construct(array $rows)
    {
        foreach ($rows as $row) {
            $this->byKey[(string) $row->key][] = new TallyRow((int) $row->a, (int) $row->b, (int) $row->n, (int) $row->sum, (int) $row->best);
        }
    }

    /** @return list<TallyRow> */
    public function of(Tally $key): array
    {
        return $this->byKey[$key->value] ?? [];
    }

    /**
     * How many a key counted where `a` is one of these ids and `b` one of those. A side left out or null matches
     * anything; an empty list matches nothing, so a spell the character never cast counts no rows.
     *
     * @param  list<int>|null  $a
     * @param  list<int>|null  $b
     */
    public function count(Tally $key, ?array $a = null, ?array $b = null): int
    {
        $n = 0;
        foreach ($this->of($key) as $row) {
            if (($a === null || in_array($row->a, $a, true)) && ($b === null || in_array($row->b, $b, true))) {
                $n += $row->n;
            }
        }

        return $n;
    }
}
