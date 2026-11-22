<?php

namespace App\Support;

use JsonSerializable;

/**
 * The difference between two arms of one source, such as a loot rule against another or a group size against another
 * (docs/LOOT.md, Statistical treatment, The difference): the second arm's rate minus the first's in points, with the
 * Newcombe range. It is `marked` only when both arms have 30 windows and the range stays off zero after the family
 * quantile of the items compared, and a mark is a counting fault until shown otherwise, so it carries the tags of both
 * arms. Under 10 windows in an arm there is no difference at all.
 */
final readonly class RateDifference implements JsonSerializable
{
    /**
     * @param  list<RateFlag>  $flags
     */
    private function __construct(
        public float $points,
        public float $low,
        public float $high,
        public bool $marked,
        public array $flags,
    ) {}

    /**
     * Null when either arm is under 10 windows. `$items` is the number of items compared in the source, which sizes
     * the range a mark has to clear; `$sharedCharacters` is false when the two arms have no character in common.
     */
    public static function between(RateEstimate $first, RateEstimate $second, int $items, bool $sharedCharacters): ?self
    {
        if (! $first->stated() || ! $second->stated()) {
            return null;
        }
        $plain = RateMath::newcombe($first->k, $first->n, $second->k, $second->n);
        $family = RateMath::newcombe($first->k, $first->n, $second->k, $second->n, RateMath::familyZ($items));
        $solid = $first->sample === RateSample::Solid && $second->sample === RateSample::Solid;
        $marked = $solid && ($plain['low'] > 0 || $plain['high'] < 0) && ($family['low'] > 0 || $family['high'] < 0);
        $carried = $marked ? [...$first->flags, ...$second->flags, ...($sharedCharacters ? [] : [RateFlag::DifferentCharacters])] : [];

        return new self($plain['difference'], $plain['low'], $plain['high'], $marked, array_values(array_filter(RateFlag::cases(), fn (RateFlag $flag): bool => in_array($flag, $carried, true))));
    }

    /** `+24.9 points`, with a minus sign for a fall. */
    public function words(): string
    {
        return self::signed($this->points).' points';
    }

    /** `9.8 to 40.6`, in points. */
    public function range(): string
    {
        return number_format($this->low * 100, 1).' to '.number_format($this->high * 100, 1);
    }

    public static function signed(float $share): string
    {
        return ($share < 0 ? '-' : '+').number_format(abs($share * 100), 1);
    }

    /**
     * @return array{difference: float, low: float, high: float, marked: bool, flags: list<string>}
     */
    public function jsonSerialize(): array
    {
        return [
            'difference' => round($this->points, 4), 'low' => round($this->low, 4), 'high' => round($this->high, 4), 'marked' => $this->marked,
            'flags' => array_map(fn (RateFlag $flag): string => $flag->value, $this->flags),
        ];
    }
}
