<?php

namespace App\Support;

use App\View\Format;

/** The leaderboards. The value is the name in URLs and in the API (`/leaderboards/{board}`). */
enum Board: string
{
    case FastestTo60 = 'fastest-to-60';
    case FastestTo40 = 'fastest-to-40';
    case FastestTo20 = 'fastest-to-20';
    case HighestWithoutDying = 'highest-level-without-dying';
    case MostQuests = 'most-quests';
    case MostQuestsCompleted = 'most-quests-completed';
    case MostKills = 'most-kills';
    case MostJumps = 'most-jumps';
    case MostDungeonRuns = 'most-dungeon-runs';
    case MostDistance = 'most-distance';
    case MostTimeByTheFire = 'most-time-by-the-fire';
    case MostFishCaught = 'most-fish-caught';
    case MostHerbsGathered = 'most-herbs-gathered';
    case MostOreMined = 'most-ore-mined';
    case MostCreaturesSkinned = 'most-creatures-skinned';
    case MostAlchemyCrafts = 'most-alchemy-crafts';
    case MostBlacksmithingCrafts = 'most-blacksmithing-crafts';
    case MostEnchantingCrafts = 'most-enchanting-crafts';
    case MostEngineeringCrafts = 'most-engineering-crafts';
    case MostLeatherworkingCrafts = 'most-leatherworking-crafts';
    case MostTailoringCrafts = 'most-tailoring-crafts';
    case MostCookingCrafts = 'most-cooking-crafts';
    case MostFirstAidCrafts = 'most-first-aid-crafts';

    /** The board of a gathering: its most looted attempts. */
    public static function ofGathering(GatherKind $kind): self
    {
        return match ($kind) {
            GatherKind::Fish => self::MostFishCaught,
            GatherKind::Herb => self::MostHerbsGathered,
            GatherKind::Ore => self::MostOreMined,
            GatherKind::Skin => self::MostCreaturesSkinned,
        };
    }

    /** The gathering a board counts, null for the others. */
    public function gathering(): ?GatherKind
    {
        return match ($this) {
            self::MostFishCaught => GatherKind::Fish,
            self::MostHerbsGathered => GatherKind::Herb,
            self::MostOreMined => GatherKind::Ore,
            self::MostCreaturesSkinned => GatherKind::Skin,
            default => null,
        };
    }

    /** The board of a crafting profession: its most crafts. Null for a profession that does not craft. */
    public static function ofCrafting(Profession $profession): ?self
    {
        return match ($profession) {
            Profession::Alchemy => self::MostAlchemyCrafts,
            Profession::Blacksmithing => self::MostBlacksmithingCrafts,
            Profession::Enchanting => self::MostEnchantingCrafts,
            Profession::Engineering => self::MostEngineeringCrafts,
            Profession::Leatherworking => self::MostLeatherworkingCrafts,
            Profession::Tailoring => self::MostTailoringCrafts,
            Profession::Cooking => self::MostCookingCrafts,
            Profession::FirstAid => self::MostFirstAidCrafts,
            default => null,
        };
    }

    /** The crafting profession a board counts, null for the others. */
    public function crafting(): ?Profession
    {
        return match ($this) {
            self::MostAlchemyCrafts => Profession::Alchemy,
            self::MostBlacksmithingCrafts => Profession::Blacksmithing,
            self::MostEnchantingCrafts => Profession::Enchanting,
            self::MostEngineeringCrafts => Profession::Engineering,
            self::MostLeatherworkingCrafts => Profession::Leatherworking,
            self::MostTailoringCrafts => Profession::Tailoring,
            self::MostCookingCrafts => Profession::Cooking,
            self::MostFirstAidCrafts => Profession::FirstAid,
            default => null,
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::FastestTo60, self::FastestTo40, self::FastestTo20 => 'Fastest to level '.$this->level(),
            self::HighestWithoutDying => 'Highest level without dying',
            self::MostQuests => 'Most quests turned in',
            self::MostQuestsCompleted => 'Most quests completed',
            self::MostKills => 'Most kills',
            self::MostJumps => 'Most jumps',
            self::MostDungeonRuns => 'Most dungeon runs',
            self::MostDistance => 'Most distance travelled',
            self::MostTimeByTheFire => 'Most time by the fire',
            self::MostFishCaught => 'Most fish caught',
            self::MostHerbsGathered => 'Most herbs gathered',
            self::MostOreMined => 'Most ore mined',
            self::MostCreaturesSkinned => 'Most creatures skinned',
            self::MostAlchemyCrafts, self::MostBlacksmithingCrafts, self::MostEnchantingCrafts, self::MostEngineeringCrafts,
            self::MostLeatherworkingCrafts, self::MostTailoringCrafts, self::MostCookingCrafts, self::MostFirstAidCrafts => 'Most '.$this->crafting()?->value.' crafts',
        };
    }

    /** The glyph of the board, a name of `<x-icon>`. */
    public function icon(): string
    {
        return match ($this) {
            self::FastestTo60, self::FastestTo40, self::FastestTo20 => 'level',
            self::HighestWithoutDying => 'shield',
            self::MostQuests, self::MostQuestsCompleted => 'quests',
            self::MostKills => 'kills',
            self::MostJumps => 'jumps',
            self::MostDungeonRuns => 'dungeons',
            self::MostDistance => 'distance',
            self::MostTimeByTheFire => 'hours',
            self::MostFishCaught => 'fish',
            self::MostHerbsGathered, self::MostOreMined, self::MostCreaturesSkinned => 'gather',
            self::MostAlchemyCrafts, self::MostBlacksmithingCrafts, self::MostEnchantingCrafts, self::MostEngineeringCrafts,
            self::MostLeatherworkingCrafts, self::MostTailoringCrafts, self::MostCookingCrafts, self::MostFirstAidCrafts => 'skills',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FastestTo60, self::FastestTo40, self::FastestTo20 => 'The client\'s played time when the character reached level '.$this->level().'. Only public characters whose level-up to '.$this->level().' Scarlet recorded are listed.',
            self::HighestWithoutDying => 'The highest level reached by public characters that Scarlet recorded from their first level, with no death and no play missing.',
            self::MostQuests => 'Quests turned in by public characters, counted from each one\'s first recorded session.',
            self::MostQuestsCompleted => 'Quests the game says public characters have completed over their whole life, as the latest read Scarlet recorded for each. It includes quests done before Scarlet was installed.',
            self::MostKills => 'Creatures killed by public characters, counted from each one\'s first recorded session.',
            self::MostJumps => 'Jumps by public characters, counted from each one\'s first recorded session.',
            self::MostDungeonRuns => 'Dungeon runs by public characters, counted from each one\'s first recorded session.',
            self::MostDistance => 'The length of the recorded path on the world map, in world widths: 1.0 is the width of the map. Teleports and loading screens are left out.',
            self::MostTimeByTheFire => 'Time spent near a campfire by public characters, counted from each one\'s first recorded session.',
            self::MostFishCaught => 'Casts that caught something, by public characters, counted from each one\'s first recorded session.',
            self::MostHerbsGathered => 'Herbs gathered by public characters, counted from each one\'s first recorded session.',
            self::MostOreMined => 'Veins mined by public characters, counted from each one\'s first recorded session.',
            self::MostCreaturesSkinned => 'Creatures skinned by public characters, counted from each one\'s first recorded session.',
            self::MostAlchemyCrafts, self::MostBlacksmithingCrafts, self::MostEnchantingCrafts, self::MostEngineeringCrafts,
            self::MostLeatherworkingCrafts, self::MostTailoringCrafts, self::MostCookingCrafts, self::MostFirstAidCrafts => 'Items crafted with '.$this->crafting()?->value.' by public characters, counted from each one\'s first recorded session.',
        };
    }

    /** The level a "fastest to" board races to, or null. */
    public function level(): ?int
    {
        return match ($this) {
            self::FastestTo60 => 60,
            self::FastestTo40 => 40,
            self::FastestTo20 => 20,
            default => null,
        };
    }

    /** The heading of the value column. */
    public function column(): string
    {
        return match ($this) {
            self::FastestTo60, self::FastestTo40, self::FastestTo20 => 'Played to '.$this->level(),
            self::HighestWithoutDying => 'Level',
            self::MostQuests => 'Quests',
            self::MostQuestsCompleted => 'Quests completed',
            self::MostKills => 'Kills',
            self::MostJumps => 'Jumps',
            self::MostDungeonRuns => 'Runs',
            self::MostDistance => 'World widths',
            self::MostTimeByTheFire => 'Time by the fire',
            self::MostFishCaught => 'Fish',
            self::MostHerbsGathered => 'Herbs',
            self::MostOreMined => 'Veins',
            self::MostCreaturesSkinned => 'Creatures',
            self::MostAlchemyCrafts, self::MostBlacksmithingCrafts, self::MostEnchantingCrafts, self::MostEngineeringCrafts,
            self::MostLeatherworkingCrafts, self::MostTailoringCrafts, self::MostCookingCrafts, self::MostFirstAidCrafts => 'Crafts',
        };
    }

    /** The heading of the last column: a fastest board shows the day the level was reached. */
    public function dateColumn(): string
    {
        return $this->level() === null ? 'Last played' : 'Reached';
    }

    /** A board value as the table shows it. */
    public function format(int|float $value): string
    {
        return match ($this) {
            self::FastestTo60, self::FastestTo40, self::FastestTo20 => Format::exact($value),
            self::MostDistance => Format::number($value, 1),
            self::MostTimeByTheFire => Format::hours($value),
            default => Format::number($value),
        };
    }
}
