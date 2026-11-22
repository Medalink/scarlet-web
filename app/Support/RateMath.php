<?php

namespace App\Support;

/**
 * The statistics behind every rate and every comparison of rates (docs/LOOT.md, Statistical treatment): the Wilson
 * score range, the Newcombe range of a difference built from two of them, the normal quantile that sizes a range for
 * several comparisons, and the Mantel-Haenszel pooling of a difference over sources. Nothing else computes a range.
 */
final class RateMath
{
    /** The normal quantile of a two-sided 95% range. */
    public const float Z95 = 1.959964;

    private const float FAMILY_ALPHA = 0.05;

    /**
     * The quantile of a two-sided range that holds across `$comparisons` comparisons at once: 0.05 divided by their
     * number, split over both tails.
     */
    public static function familyZ(int $comparisons): float
    {
        return self::quantile(1 - self::FAMILY_ALPHA / (2 * max(1, $comparisons)));
    }

    /**
     * The Wilson score range of `$k` of `$n`, as a share. It behaves at k = 0 and at small n and stays inside 0 to 1.
     *
     * @return array{low: float, high: float}
     */
    public static function wilson(int $k, int $n, float $z = self::Z95): array
    {
        if ($n <= 0) {
            return ['low' => 0.0, 'high' => 1.0];
        }
        $p = $k / $n;
        $z2 = $z ** 2;
        $centre = ($p + $z2 / (2 * $n)) / (1 + $z2 / $n);
        $spread = $z * sqrt($p * (1 - $p) / $n + $z2 / (4 * $n ** 2)) / (1 + $z2 / $n);

        return ['low' => max(0.0, $centre - $spread), 'high' => min(1.0, $centre + $spread)];
    }

    /**
     * The difference of the second share from the first, `$second - $first`, with its Newcombe range: the two Wilson
     * ranges combined by the square roots of their squared distances to the shares.
     *
     * @return array{difference: float, low: float, high: float}
     */
    public static function newcombe(int $firstK, int $firstN, int $secondK, int $secondN, float $z = self::Z95): array
    {
        $first = self::wilson($firstK, $firstN, $z);
        $second = self::wilson($secondK, $secondN, $z);
        $p1 = $firstN > 0 ? $firstK / $firstN : 0.0;
        $p2 = $secondN > 0 ? $secondK / $secondN : 0.0;
        $difference = $p2 - $p1;

        return [
            'difference' => $difference,
            'low' => $difference - sqrt(($p2 - $second['low']) ** 2 + ($first['high'] - $p1) ** 2),
            'high' => $difference + sqrt(($second['high'] - $p2) ** 2 + ($p1 - $first['low']) ** 2),
        ];
    }

    /**
     * The mean difference of a measure that is not a share, `second - first`, pooled over sources with Mantel-Haenszel
     * weights n1 x n2 / (n1 + n2) and a normal range from each source's variance. A source needs two windows in each arm
     * for a variance. Null when no source can be pooled.
     *
     * @param  list<array{n1: int, sum1: int, squares1: int, n2: int, sum2: int, squares2: int}>  $sources
     * @return array{difference: float, low: float, high: float, first: float, second: float, sources: int, windows: int}|null
     */
    public static function pooledDifference(array $sources, float $z = self::Z95): ?array
    {
        $weight = $difference = $variance = $first = $second = 0.0;
        $count = $windows = 0;
        foreach ($sources as $source) {
            if ($source['n1'] < 2 || $source['n2'] < 2) {
                continue;
            }
            $w = $source['n1'] * $source['n2'] / ($source['n1'] + $source['n2']);
            $mean1 = $source['sum1'] / $source['n1'];
            $mean2 = $source['sum2'] / $source['n2'];
            $weight += $w;
            $first += $w * $mean1;
            $second += $w * $mean2;
            $difference += $w * ($mean2 - $mean1);
            $variance += $w ** 2 * (self::sampleVariance($source['n1'], $source['sum1'], $source['squares1']) / $source['n1'] + self::sampleVariance($source['n2'], $source['sum2'], $source['squares2']) / $source['n2']);
            $count++;
            $windows += $source['n1'] + $source['n2'];
        }
        if ($count === 0) {
            return null;
        }
        $mean = $difference / $weight;
        $spread = $z * sqrt($variance) / $weight;

        return ['difference' => $mean, 'low' => $mean - $spread, 'high' => $mean + $spread, 'first' => $first / $weight, 'second' => $second / $weight, 'sources' => $count, 'windows' => $windows];
    }

    /**
     * The normal quantile for a probability (Acklam's rational approximation, relative error under 1.2e-9).
     */
    public static function quantile(float $p): float
    {
        $a = [-39.69683028665376, 220.9460984245205, -275.9285104469687, 138.357751867269, -30.66479806614716, 2.506628277459239];
        $b = [-54.47609879822406, 161.5858368580409, -155.6989798598866, 66.80131188771972, -13.28068155288572];
        $c = [-0.007784894002430293, -0.3223964580411365, -2.400758277161838, -2.549732539343734, 4.374664141464968, 2.938163982698783];
        $d = [0.007784695709041462, 0.3224671290700398, 2.445134137142996, 3.754408661907416];
        $edge = 0.02425;
        if ($p < $edge) {
            $q = sqrt(-2 * log($p));

            return ((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5]) / (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1);
        }
        if ($p > 1 - $edge) {
            return -self::quantile(1 - $p);
        }
        $q = $p - 0.5;
        $r = $q * $q;

        return ((((($a[0] * $r + $a[1]) * $r + $a[2]) * $r + $a[3]) * $r + $a[4]) * $r + $a[5]) * $q / ((((($b[0] * $r + $b[1]) * $r + $b[2]) * $r + $b[3]) * $r + $b[4]) * $r + 1);
    }

    private static function sampleVariance(int $n, int $sum, int $squares): float
    {
        return max(0.0, ($squares - $sum ** 2 / $n) / ($n - 1));
    }
}
