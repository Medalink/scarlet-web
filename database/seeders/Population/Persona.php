<?php

namespace Database\Seeders\Population;

/**
 * What a character does with its days. The case drives the session scripts: how often it plays, how much it
 * fights, whether it gathers, crafts, runs dungeons, dies a lot, sits at campfires or earns copper.
 */
enum Persona: string
{
    case Leveler = 'leveler';
    case Gatherer = 'gatherer';
    case Crafter = 'crafter';
    case DungeonRunner = 'dungeon';
    case Hardcore = 'hardcore';
    case Camper = 'camper';
    case PvPer = 'pvp';

    /** How the population spreads over the cases, weights out of 1000. */
    public const array WEIGHTS = [
        self::Leveler->value => 380,
        self::Gatherer->value => 130,
        self::Crafter->value => 90,
        self::DungeonRunner->value => 150,
        self::Hardcore->value => 90,
        self::Camper->value => 60,
        self::PvPer->value => 100,
    ];

    public static function draw(Rng $rng): self
    {
        $roll = $rng->interval(1, 1000);
        $gone = 0;

        foreach (self::WEIGHTS as $value => $weight) {
            $gone += $weight;
            if ($roll <= $gone) {
                return self::from($value);
            }
        }

        return self::Leveler;
    }

    /** The hours of one session, from and to. */
    public function sessionHours(): array
    {
        return match ($this) {
            self::DungeonRunner => [1.5, 3.5],
            self::Camper => [0.7, 1.6],
            self::Hardcore => [2.0, 5.0],
            default => [1.0, 3.0],
        };
    }

    /** The fights of one hour of play, from and to; zero for a crafter, who mostly stands at the forge. */
    public function fightsPerHour(): array
    {
        return match ($this) {
            self::Leveler, self::Hardcore => [14, 26],
            self::Gatherer => [4, 9],
            self::Crafter => [1, 4],
            self::DungeonRunner => [10, 18],
            self::Camper => [3, 8],
            self::PvPer => [12, 22],
        };
    }

    /** Sessions out of 100 that hold a dungeon run. */
    public function dungeonChance(): int
    {
        return match ($this) {
            self::DungeonRunner => 85,
            self::Leveler => 12,
            self::Hardcore => 18,
            default => 4,
        };
    }

    /** Gather blocks of one session, from and to; zero when the persona never gathers. */
    public function gatherBlocks(): array
    {
        return match ($this) {
            self::Gatherer => [3, 7],
            self::Leveler => [0, 2],
            self::Hardcore => [0, 1],
            default => [0, 0],
        };
    }

    /** Craft blocks of one session; zero when the persona never crafts. */
    public function craftBlocks(): array
    {
        return match ($this) {
            self::Crafter => [2, 5],
            self::Leveler => [0, 1],
            default => [0, 0],
        };
    }

    /** Deaths of one session, from and to; a hardcore death ends a life, so it is rarer. */
    public function deathsPerSession(): array
    {
        return match ($this) {
            self::Hardcore => [0, 0],
            self::Leveler => [0, 2],
            self::PvPer => [0, 3],
            self::DungeonRunner => [0, 1],
            default => [0, 1],
        };
    }

    /** Quest lines of one session, from and to. */
    public function questsPerSession(): array
    {
        return match ($this) {
            self::Leveler => [1, 4],
            self::Hardcore => [1, 3],
            self::DungeonRunner => [0, 2],
            default => [0, 1],
        };
    }

    /** Campfire visits of one session. */
    public function campChance(): int
    {
        return match ($this) {
            self::Camper => 90,
            self::Gatherer => 25,
            default => 8,
        };
    }

    /** True for a persona whose character records its economy. */
    public function showsEconomy(): bool
    {
        return in_array($this, [self::Crafter, self::Gatherer, self::DungeonRunner], true);
    }
}
