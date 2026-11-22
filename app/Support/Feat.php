<?php

namespace App\Support;

/** The feats of a character page: what each counts, its icon and the four thresholds of its tiers (docs/DESIGN.md "Art and game layer"). */
enum Feat: string
{
    case Summit = 'summit';
    case Slayer = 'slayer';
    case Questing = 'questing';
    case Delver = 'delver';
    case Explorer = 'explorer';
    case Veteran = 'veteran';
    case Wayfarer = 'wayfarer';
    case Unbroken = 'unbroken';
    case Artisan = 'artisan';
    case Angler = 'angler';
    case Gatherer = 'gatherer';

    /** The tiers in order, copper first: tier `n` of a feat is `TIERS[n - 1]`, and 0 is no tier. */
    public const array TIERS = ['Copper', 'Silver', 'Gold', 'Scarlet'];

    /** The tier a count reaches: 0 for none, 1 copper to 4 scarlet. */
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

    /** @return array{0: int, 1: int, 2: int, 3: int} the counts that earn copper, silver, gold and scarlet */
    public function thresholds(): array
    {
        return match ($this) {
            self::Summit => [15, 30, 45, 60],
            self::Slayer => [250, 2500, 10000, 50000],
            self::Questing => [50, 250, 750, 1500],
            self::Delver => [5, 25, 75, 200],
            self::Explorer => [10, 20, 35, 50],
            self::Veteran => [10, 50, 150, 500],
            self::Wayfarer => [10, 50, 150, 500],
            self::Unbroken => [10, 20, 40, 60],
            self::Artisan => [75, 150, 225, 300],
            self::Angler => [50, 250, 1000, 5000],
            self::Gatherer => [50, 250, 1000, 5000],
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
            self::Wayfarer => 'distance',
            self::Unbroken => 'shield',
            self::Artisan => 'skills',
            self::Angler => 'fish',
            self::Gatherer => 'gather',
        };
    }

    /** The count as the page says it: "6,969 kills", "1 kill", and "Level 42" for a feat that is a level or a rank. */
    public function says(string $number, bool $one = false): string
    {
        return match ($this) {
            self::Summit => 'Level '.$number,
            self::Unbroken => 'Level '.$number.' without dying',
            self::Artisan => 'Profession rank '.$number,
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
            self::Wayfarer => $one ? 'world width walked' : 'world widths walked',
            self::Unbroken => $one ? 'level reached without dying' : 'levels reached without dying',
            self::Artisan => $one ? 'profession rank' : 'profession ranks',
            self::Angler => 'fish caught',
            self::Gatherer => $one ? 'node gathered' : 'nodes gathered',
        };
    }
}
