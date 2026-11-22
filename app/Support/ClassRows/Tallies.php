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
     * How many a key counted where `a` is one of these ids and `b` one of those; an empty list matches anything.
     *
     * @param  list<int>  $a
     * @param  list<int>  $b
     */
    public function count(Tally $key, array $a = [], array $b = []): int
    {
        $n = 0;
        foreach ($this->of($key) as $row) {
            if (($a === [] || in_array($row->a, $a, true)) && ($b === [] || in_array($row->b, $b, true))) {
                $n += $row->n;
            }
        }

        return $n;
    }
}
