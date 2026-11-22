<?php

namespace App\Support;

/** The feats of a character page: what each counts, its icon and the five thresholds of its tiers (docs/DESIGN.md "Art and game layer"). */
enum Feat: string
{
    case Summit = 'summit';
    case Slayer = 'slayer';
    case Questing = 'questing';
    case Delver = 'delver';
    case Explorer = 'explorer';
    case Veteran = 'veteran';
    case Devoted = 'devoted';
    case Wayfarer = 'wayfarer';
    case Jumper = 'jumper';
    case Swimmer = 'swimmer';
    case Unbroken = 'unbroken';
    case Artisan = 'artisan';
    case Crafter = 'crafter';
    case Angler = 'angler';
    case Gatherer = 'gatherer';
    case Tracker = 'tracker';

    /** The tiers in order, copper first: tier `n` of a feat is `TIERS[n - 1]`, and 0 is no tier. */
    public const array TIERS = ['Copper', 'Silver', 'Gold', 'Scarlet', 'Legend'];

    /** The tier a count reaches: 0 for none, 1 copper to 5 legend. */
    public function tier(int|float $value): int
    {
        $tier = 0;
        foreach ($this->thresholds() as $i => $threshold) {
            if ($value >= $threshold) {
                $tier = $i + 1;
            }
        }

        return $tier;
    }

    /** @return array{0: int, 1: int, 2: int, 3: int, 4: int} the counts that earn copper, silver, gold, scarlet and legend */
    public function thresholds(): array
    {
        return match ($this) {
            self::Summit => [10, 25, 45, 55, 60],
            self::Slayer => [100, 2500, 10000, 30000, 150000],
            self::Questing => [10, 150, 500, 1000, 1500],
            self::Delver => [1, 20, 75, 200, 500],
            self::Explorer => [5, 20, 40, 55, 65],
            self::Veteran => [2, 50, 200, 500, 2500],
            self::Devoted => [3, 14, 60, 150, 365],
            self::Wayfarer => [1, 15, 60, 150, 750],
            self::Jumper => [100, 5000, 20000, 50000, 250000],
            self::Swimmer => [500, 10000, 50000, 125000, 600000],
            self::Unbroken => [10, 20, 35, 50, 60],
            self::Artisan => [75, 300, 750, 1350, 1500],
            self::Crafter => [5, 500, 2500, 10000, 25000],
            self::Angler => [10, 250, 1500, 5000, 15000],
            self::Gatherer => [10, 250, 1500, 5000, 15000],
            self::Tracker => [1, 10, 30, 60, 100],
        };
    }

    /** The name of the icon in `<x-icon>`. */
    public function icon(): string
    {
        return match ($this) {
            self::Summit => 'level',
            self::Slayer => 'kills',
            self::Questing => 'quests',
            self::Delver => 'dungeons',
            self::Explorer => 'zones',
            self::Veteran => 'hours',
            self::Devoted => 'clock',
            self::Wayfarer => 'distance',
            self::Jumper => 'jumps',
            self::Swimmer => 'swim',
            self::Unbroken => 'shield',
            self::Artisan => 'skills',
            self::Crafter => 'item',
            self::Angler => 'fish',
            self::Gatherer => 'gather',
            self::Tracker => 'pin',
        };
    }

    /** The count as the page says it: "6,969 kills", "1 kill", and "Level 42" for a feat that is a level or a rank. */
    public function says(string $number, bool $one = false): string
    {
        return match ($this) {
            self::Summit => 'Level '.$number,
            self::Unbroken => 'Level '.$number.' without dying',
            default => $number.' '.$this->noun($one),
        };
    }

    /** What the count is of, after the number: "6,969 kills", or "1 kill" when `$one`. */
    public function noun(bool $one = false): string
    {
        return match ($this) {
            self::Summit => $one ? 'level reached' : 'levels reached',
            self::Slayer => $one ? 'kill' : 'kills',
            self::Questing => $one ? 'quest turned in' : 'quests turned in',
            self::Delver => $one ? 'dungeon run' : 'dungeon runs',
            self::Explorer => $one ? 'zone entered' : 'zones entered',
            self::Veteran => $one ? 'hour played' : 'hours played',
            self::Devoted => $one ? 'day played' : 'days played',
            self::Wayfarer => $one ? 'world width walked' : 'world widths walked',
            self::Jumper => $one ? 'jump' : 'jumps',
            self::Swimmer => $one ? 'yard swum' : 'yards swum',
            self::Unbroken => $one ? 'level reached without dying' : 'levels reached without dying',
            self::Artisan => $one ? 'profession rank' : 'profession ranks',
            self::Crafter => $one ? 'item crafted' : 'items crafted',
            self::Angler => 'fish caught',
            self::Gatherer => $one ? 'node gathered' : 'nodes gathered',
            self::Tracker => $one ? 'rare creature found' : 'rare creatures found',
        };
    }
}
