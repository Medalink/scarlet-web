<?php

namespace App\View\Design\ClassPages;

use App\Support\ClassPage\FeatCard;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Unit;
use App\View\Design\Rng;

/** What the invented class pages share: rounding as the comps' script does it, and feat cards built the way the feat wall reads them. */
final class Invent
{
    /** Math.round: halves go up. */
    public static function round(float|int $value): int
    {
        return (int) floor($value + 0.5);
    }

    /** A percentage of a whole, rounded: 31 of 100 → 31. */
    public static function percent(float|int $part, float|int $whole): int
    {
        return self::round($part / $whole * 100);
    }

    /**
     * Shares of each series by level from a weight function, with the comps' seeded random numbers.
     *
     * @param  callable(int): list<float|int>  $weights  the weights of a level, one for each series
     * @param  float  $low  the smallest factor a weight is jittered by, and `$span` the range above it
     * @return list<list<float>>
     */
    public static function shares(int $seed, int $levels, callable $weights, float $low = 0.8, float $span = 0.4): array
    {
        $rng = new Rng($seed);
        $rows = [];
        for ($level = 1; $level <= $levels; $level++) {
            $jittered = array_map(fn (float|int $weight): float => $weight * ($low + $rng->next() * $span), $weights($level));
            $total = array_sum($jittered);
            $rows[] = array_map(fn (float $value): float => $total > 0 ? $value / $total : $value, $jittered);
        }

        return $rows;
    }

    /**
     * Feat cards, the nearest to its next tier first.
     *
     * @param  list<array{0: Icon, 1: string, 2: int|float, 3: list<int>, 4: string}>  $rows  icon, name, count, the five thresholds, what the count is of
     * @return list<FeatCard>
     */
    public static function feats(array $rows): array
    {
        $cards = [];
        foreach ($rows as [$icon, $name, $value, $steps, $what]) {
            $tier = count(array_filter($steps, fn (int $step): bool => $value >= $step));
            $floor = $tier > 0 ? $steps[$tier - 1] : 0;
            $next = $steps[$tier] ?? null;
            $part = $next === null ? 100 : self::round(($value - $floor) / ($next - $floor) * 100);
            $track = [];
            foreach (array_keys($steps) as $i) {
                $track[] = $i < $tier ? 100 : ($i === $tier ? $part : 0);
            }
            $cards[] = [
                'order' => $tier * 1000 + $part,
                'card' => new FeatCard($name, $tier, Unit::Count->say($value).($next === null ? '' : ' of '.Unit::Count->say($next)).' '.$what, $track, 'spell', $icon),
            ];
        }
        usort($cards, fn (array $a, array $b): int => $b['order'] <=> $a['order']);

        return array_map(fn (array $entry): FeatCard => $entry['card'], $cards);
    }
}
