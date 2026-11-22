<?php

namespace App\Support;

/**
 * The four gathering skills that `gathers` and `gather_items` rows name by `kind` (docs/ROWS.md). The decoder keeps the
 * same spell ids and skill lines in part/src/professions.rs.
 */
enum GatherKind: string
{
    case Fish = 'fish';
    case Herb = 'herb';
    case Ore = 'ore';
    case Skin = 'skin';

    /** The `LO` object id of a fishing bobber: a looted catch from any other source is a school's. */
    public const int BOBBER = 35591;

    /** How long a lure lasts, in seconds: a whole number of minutes, 1800 at most (the aggregate groups every older lure as one). This is the classic length and no recording has confirmed it. */
    public const int LURE_SECONDS = 600;

    /** The rank a gathering skill stops at. */
    public const int SKILL_CAP = Profession::SKILL_CAP;

    /** The skill line the gathering skill's rank is read from; a child line (2943, 2944, 2946, 2947) counts as it. */
    public function skillLine(): int
    {
        return match ($this) {
            self::Fish => 356,
            self::Herb => 182,
            self::Ore => 186,
            self::Skin => 393,
        };
    }

    /** @return list<int> the spells that gather: one rank each, and the later expansions' */
    public function spells(): array
    {
        return match ($this) {
            self::Fish => [7620, 7731, 7732, 18248, 1240948, 1240959, 1240970, 1240983],
            self::Herb => [2366, 2368, 2369, 2371, 3570, 11993, 1235236, 1240949, 1240960, 1240971, 1240984],
            self::Ore => [2575, 2576, 2577, 2578, 2579, 3564, 10248, 1235230, 1240951, 1240962, 1240973, 1240986],
            self::Skin => [8613, 8617, 8618, 10768, 1240952, 1240963, 1240974, 1240987],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Fish => 'Fishing',
            self::Herb => 'Herbalism',
            self::Ore => 'Mining',
            self::Skin => 'Skinning',
        };
    }

    public function scene(): Scene
    {
        return match ($this) {
            self::Fish => Scene::Fishing,
            self::Herb => Scene::Herbalism,
            self::Ore => Scene::Mining,
            self::Skin => Scene::Skinning,
        };
    }

    /** Whether the time between casts is time at the water: fishing waits for a bite, the others gather one thing at a time. */
    public function countsGaps(): bool
    {
        return $this === self::Fish;
    }

    public static function ofSpell(int $spell): ?self
    {
        foreach (self::cases() as $kind) {
            if (in_array($spell, $kind->spells(), strict: true)) {
                return $kind;
            }
        }

        return null;
    }
}
