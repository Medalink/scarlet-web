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

    /**
     * `split`, with no key taking more than its cap: what a key gets past its cap goes to the keys still under theirs, in
     * proportion to their weights (or their room, when none of them has weight). A total above the sum of the caps leaves
     * the rest on the keys as `split` gave it.
     *
     * @param  array<array-key, int>  $weights
     * @param  array<array-key, int>  $caps
     * @return array<array-key, int>
     */
    public static function capped(array $weights, array $caps, int $total): array
    {
        $parts = self::split($weights, $total);
        if ($total > array_sum($caps)) {
            return $parts;
        }
        while (true) {
            $over = 0;
            foreach ($parts as $key => $part) {
                if ($part > $caps[$key]) {
                    $over += $part - $caps[$key];
                    $parts[$key] = $caps[$key];
                }
            }
            if ($over === 0) {
                return $parts;
            }
            $open = array_filter($parts, fn (int $part, int|string $key): bool => $part < $caps[$key], ARRAY_FILTER_USE_BOTH);
            $openWeights = array_intersect_key($weights, $open);
            if (array_sum($openWeights) <= 0) {
                $openWeights = array_map(fn (int|string $key): int => $caps[$key] - $parts[$key], array_combine(array_keys($open), array_keys($open)));
            }
            foreach (self::split($openWeights, $over) as $key => $extra) {
                $parts[$key] += $extra;
            }
        }
    }
}
