<?php

namespace App\Support;

use JsonSerializable;

/**
 * A rate stated as the counts it rests on: `k` windows that listed the item over `n` windows, with the 95% Wilson
 * score range and the step of the sample ladder (docs/LOOT.md, Statistical treatment). Every page and every API
 * answer that shows a drop rate builds it here, so no caller formats or ranks a rate itself.
 */
final readonly class RateEstimate implements JsonSerializable
{
    /** The normal quantile of a two-sided 95% range. */
    private const float Z = 1.959964;

    public const string UNIT_WINDOW = 'window';

    /**
     * @param  list<RateFlag>  $flags
     */
    private function __construct(
        public int $k,
        public int $n,
        public float $rate,
        public float $low,
        public float $high,
        public RateSample $sample,
        public string $unit,
        public array $flags,
    ) {}

    /**
     * @param  list<RateFlag>  $flags
     */
    public static function of(int $k, int $n, array $flags = [], string $unit = self::UNIT_WINDOW): self
    {
        $n = max(0, $n);
        $k = min(max(0, $k), $n);
        if ($n === 0) {
            return new self($k, $n, 0.0, 0.0, 1.0, RateSample::Counts, $unit, $flags);
        }
        $p = $k / $n;
        $z2 = self::Z ** 2;
        $centre = ($p + $z2 / (2 * $n)) / (1 + $z2 / $n);
        $spread = self::Z * sqrt($p * (1 - $p) / $n + $z2 / (4 * $n ** 2)) / (1 + $z2 / $n);

        return new self($k, $n, $p, max(0.0, $centre - $spread), min(1.0, $centre + $spread), RateSample::forWindows($n), $unit, $flags);
    }

    /** True when the rate and its range are shown: 10 windows or more. */
    public function stated(): bool
    {
        return $this->sample !== RateSample::Counts;
    }

    /** `22 of 46`. */
    public function counts(): string
    {
        return number_format($this->k).' of '.number_format($this->n);
    }

    /** The share as a percentage, with two decimals under 1%. */
    public function percent(): string
    {
        return $this->percentOf($this->rate);
    }

    /** `34.1 to 61.9`, in percent. */
    public function range(): string
    {
        return number_format($this->low * 100, 1).' to '.number_format($this->high * 100, 1);
    }

    /**
     * Orders estimates best first: the stated ones by the low end of the range, so a 2 of 2 cannot lead, then the
     * counts-only ones by what was seen.
     */
    public static function compare(self $a, self $b): int
    {
        return [$b->stated(), $b->stated() ? $b->low : $b->k, $b->n] <=> [$a->stated(), $a->stated() ? $a->low : $a->k, $a->n];
    }

    /**
     * @return array{k: int, n: int, rate: float|null, low: float|null, high: float|null, unit: string, sample: string, flags: list<string>}
     */
    public function jsonSerialize(): array
    {
        $stated = $this->stated();

        return [
            'k' => $this->k, 'n' => $this->n,
            'rate' => $stated ? round($this->rate, 4) : null,
            'low' => $stated ? round($this->low, 4) : null,
            'high' => $stated ? round($this->high, 4) : null,
            'unit' => $this->unit, 'sample' => $this->sample->value,
            'flags' => array_map(fn (RateFlag $flag): string => $flag->value, $this->flags),
        ];
    }

    private function percentOf(float $share): string
    {
        return number_format($share * 100, $share > 0 && $share < 0.01 ? 2 : 1).'%';
    }
}
