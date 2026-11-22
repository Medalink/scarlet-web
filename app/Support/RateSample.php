<?php

namespace App\Support;

/** How much a rate stands on: the sample ladder of docs/LOOT.md, the same on every page and in the API. */
enum RateSample: string
{
    /** Under 10 windows: `k of n` only, no rate, no bar, not ranked. */
    case Counts = 'counts';

    /** 10 to 29 windows: rate, range and bar, drawn hollow. Never a headline figure. */
    case Hollow = 'hollow';

    /** 30 windows and over: drawn solid. */
    case Solid = 'solid';

    public const int HOLLOW_FROM = 10;

    public const int SOLID_FROM = 30;

    public static function forWindows(int $windows): self
    {
        return match (true) {
            $windows >= self::SOLID_FROM => self::Solid,
            $windows >= self::HOLLOW_FROM => self::Hollow,
            default => self::Counts,
        };
    }
}
