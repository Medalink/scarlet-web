<?php

namespace App\Support;

/** The four threat tiers of a creature, a zone or a dungeon, and the thresholds that earn them (docs/DESIGN.md "Art and game layer"). Tier 0 is no tier: "Harmless so far". */
enum Threat: int
{
    case Risky = 1;
    case Dangerous = 2;
    case Deadly = 3;
    case Lethal = 4;

    /** Deaths per 1,000 kills that start Risky, Dangerous, Deadly and Lethal. */
    public const array CREATURE = [1, 5, 15, 40];

    /** Deaths an hour that start Risky, Dangerous, Deadly and Lethal. */
    public const array ZONE = [0.1, 0.25, 0.5, 1];

    /** Kills a creature needs before it is rated. */
    public const int CREATURE_MIN_KILLS = 50;

    /** Hours played a zone or dungeon needs before it is rated. */
    public const int ZONE_MIN_HOURS = 10;

    /**
     * The tier a rate reaches in a set of thresholds: 0 for none, 1 Risky to 4 Lethal.
     *
     * @param  array<int, int|float>  $set
     */
    public static function tier(float $rate, array $set): int
    {
        $tier = 0;
        foreach ($set as $i => $threshold) {
            if ($rate >= $threshold) {
                $tier = $i + 1;
            }
        }

        return $tier;
    }

    /** The tier's name, "Harmless so far" for 0. */
    public static function name(int $tier): string
    {
        $held = self::tryFrom($tier);

        return $held === null ? 'Harmless so far' : $held->name;
    }
}
