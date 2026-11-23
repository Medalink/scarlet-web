<?php

namespace App\Support;

/** Whole numbers shared out in proportion to weights, so the parts add up to the total. */
final class Apportion
{
    /**
     * `$total` split over the keys of `$weights` in proportion to them: each key gets the whole number below its share, and
     * the ones left over go to the keys with the largest remainders, the earlier key first on a tie. With no weight at all
     * the first key takes the whole. An empty list has no one to take it and gives an empty list.
     *
     * @param  array<array-key, int>  $weights
     * @return array<array-key, int>
     */
    public static function split(array $weights, int $total): array
    {
        $sum = array_sum($weights);
        if ($weights === []) {
            return [];
        }
        if ($sum <= 0) {
            return array_replace(array_map(fn (): int => 0, $weights), [array_key_first($weights) => $total]);
        }

        $parts = $remainders = [];
        foreach ($weights as $key => $weight) {
            $parts[$key] = intdiv($total * $weight, $sum);
            $remainders[$key] = ($total * $weight) % $sum;
        }
        $left = $total - array_sum($parts);
        $position = array_flip(array_keys($weights));
        $order = array_keys($weights);
        usort($order, fn (int|string $a, int|string $b): int => $remainders[$b] <=> $remainders[$a] ?: $position[$a] <=> $position[$b]);
        foreach (array_slice($order, 0, $left) as $key) {
            $parts[$key]++;
        }

        return $parts;
    }
}
