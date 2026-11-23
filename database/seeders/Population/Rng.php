<?php

namespace Database\Seeders\Population;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * The one source of chance in the population: a stream per key, seeded by the key, so every character, session and
 * account draws its own repeatable sequence that does not depend on call order.
 */
final class Rng
{
    private readonly Randomizer $random;

    private function __construct(string $key)
    {
        $this->random = new Randomizer(new Mt19937(crc32('scarlet-seed:'.$key)));
    }

    public static function for(string $key): self
    {
        return new self($key);
    }

    public function interval(int $from, int $to): int
    {
        return $this->random->getInt($from, $to);
    }

    /**
     * @template T
     *
     * @param  non-empty-list<T>  $choices
     * @return T
     */
    public function pick(array $choices): mixed
    {
        return $choices[$this->interval(0, count($choices) - 1)];
    }

    public function chance(int $percent): bool
    {
        return $this->interval(1, 100) <= $percent;
    }

    public function float(float $from, float $to): float
    {
        return round($from + ($to - $from) * ($this->interval(0, 1000) / 1000), 1);
    }

    /**
     * @template T
     *
     * @param  array<array-key, T>  $items
     * @return list<T>
     */
    public function shuffled(array $items): array
    {
        $shuffled = array_values($items);

        for ($at = count($shuffled) - 1; $at > 0; $at--) {
            $other = $this->interval(0, $at);
            [$shuffled[$at], $shuffled[$other]] = [$shuffled[$other], $shuffled[$at]];
        }

        return $shuffled;
    }
}
