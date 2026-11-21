<?php

namespace App\Models;

/**
 * The rulesets of WoW Forever. A ruleset is a permanent choice for each character: the game has no call that
 * names it, so a `rulesets` row is matched to a kind only by a signal that has been checked in game (`of()`).
 */
enum RulesetKind: string
{
    case Normal = 'normal';
    case Pvp = 'pvp';
    case Roleplay = 'roleplay';
    case RoleplayPvp = 'roleplay-pvp';
    case Hardcore = 'hardcore';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Pvp => 'PvP',
            self::Roleplay => 'Roleplay',
            self::RoleplayPvp => 'Roleplay PvP',
            self::Hardcore => 'Hardcore',
        };
    }

    /** What the rulesets page says the ruleset is. */
    public function summary(): string
    {
        return match ($this) {
            self::Normal => 'Quests, dungeons and monsters. Open-world PvP only if you opt in. Both factions.',
            self::Pvp => 'Open-world combat outside sanctuaries. One faction per player.',
            self::Roleplay => 'Stricter naming and behaviour rules, a PvE focus and outdoor zones separate from non-RP players. Both factions.',
            self::RoleplayPvp => 'Roleplay rules plus open-world faction PvP.',
            self::Hardcore => 'One life. Fallen characters can transfer out.',
        };
    }

    /** Hardcore is planned and not out yet, so no character can be on it and the pages show it disabled. */
    public function available(): bool
    {
        return $this !== self::Hardcore;
    }

    /**
     * The kind a combination of signals belongs to, or null when none can be told. Only the hardcore flag is
     * known to name a ruleset. The others wait for an in game check of one signal on a character of each ruleset
     * (the `RU` line: game mode record, server number, war mode locks); add the checked values here, nowhere else.
     */
    public static function of(?bool $hardcore, ?int $modeRecord): ?self
    {
        return match (true) {
            $hardcore === true => self::Hardcore,
            default => null,
        };
    }
}
