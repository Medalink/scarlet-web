<?php

namespace App\Models;

/**
 * The rulesets of WoW Forever. A ruleset is a permanent choice for each character: the game has no call that
 * names it, so a character's kind comes from the Forever Armory or from its home realm (docs/DESIGN.md, Rulesets),
 * and a `rulesets` row carries the kind of the characters filed under it.
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

    /** The part of a ruleset's slug that names the kind. No other part of a slug starts with `k`. */
    public function slugToken(): string
    {
        return match ($this) {
            self::Normal => 'kn',
            self::Pvp => 'kp',
            self::Roleplay => 'kr',
            self::RoleplayPvp => 'krp',
            self::Hardcore => 'kh',
        };
    }

    /** The kind the Forever Armory's ruleset slug means, or null for a slug this code does not know. */
    public static function fromArmory(string $slug): ?self
    {
        return match (str_replace('_', '-', strtolower(trim($slug)))) {
            'normal' => self::Normal,
            'pvp' => self::Pvp,
            'roleplay', 'rp' => self::Roleplay,
            'roleplay-pvp', 'rp-pvp' => self::RoleplayPvp,
            'hardcore' => self::Hardcore,
            default => null,
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
     * The kind a combination of recorded signals names by itself, or null. Only the hardcore flag does. The kind of
     * everything else is the kind of its character (`rulesets.kind`), which the Armory or the realm gives.
     */
    public static function of(?bool $hardcore, ?int $modeRecord): ?self
    {
        return match (true) {
            $hardcore === true => self::Hardcore,
            default => null,
        };
    }
}
