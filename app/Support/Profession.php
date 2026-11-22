<?php

namespace App\Support;

/**
 * The professions among the skills of `skill_ups`, by the skill's name. Every other skill is a weapon, armour or class
 * skill. Twelve have a page, at `slug()`; Lockpicking and Poisons have none.
 */
enum Profession: string
{
    case Alchemy = 'Alchemy';
    case Blacksmithing = 'Blacksmithing';
    case Enchanting = 'Enchanting';
    case Engineering = 'Engineering';
    case Herbalism = 'Herbalism';
    case Leatherworking = 'Leatherworking';
    case Mining = 'Mining';
    case Skinning = 'Skinning';
    case Tailoring = 'Tailoring';
    case Cooking = 'Cooking';
    case FirstAid = 'First Aid';
    case Fishing = 'Fishing';
    case Lockpicking = 'Lockpicking';
    case Poisons = 'Poisons';

    /** The path segment of the profession's page and of `/api/v1/public/professions/{slug}`, null when it has none. */
    public function slug(): ?string
    {
        return match ($this) {
            self::Lockpicking, self::Poisons => null,
            self::FirstAid => 'first-aid',
            default => strtolower($this->value),
        };
    }

    /** The skill line the rank is read from, null when the profession has no page. */
    public function skillLine(): ?int
    {
        return $this->gatherKind()?->skillLine() ?? match ($this) {
            self::Alchemy => 171,
            self::Blacksmithing => 164,
            self::Enchanting => 333,
            self::Engineering => 202,
            self::Leatherworking => 165,
            self::Tailoring => 197,
            self::Cooking => 185,
            self::FirstAid => 129,
            default => null,
        };
    }

    /** The gathering the profession is, null for a crafting profession. */
    public function gatherKind(): ?GatherKind
    {
        return match ($this) {
            self::Fishing => GatherKind::Fish,
            self::Herbalism => GatherKind::Herb,
            self::Mining => GatherKind::Ore,
            self::Skinning => GatherKind::Skin,
            default => null,
        };
    }

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $profession) {
            if ($profession->slug() === $slug) {
                return $profession;
            }
        }

        return null;
    }
}
