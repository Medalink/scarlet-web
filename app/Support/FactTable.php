<?php

namespace App\Support;

/**
 * The decoded fact hypertables (docs/ROWS.md) and what ties each to the decoder and to a sharing group.
 * A table name is also the value of the decoder's `t` key.
 */
enum FactTable: string
{
    case PlaySpans = 'play_spans';
    case LevelUps = 'level_ups';
    case XpGains = 'xp_gains';
    case Kills = 'kills';
    case Deaths = 'deaths';
    case DungeonRuns = 'dungeon_runs';
    case QuestEvents = 'quest_events';
    case MoneyChanges = 'money_changes';
    case LootOpens = 'loot_opens';
    case LootDrops = 'loot_drops';
    case SkillUps = 'skill_ups';
    case Crafts = 'crafts';
    case PathPoints = 'path_points';
    case MovementStretches = 'movement_stretches';
    case CreatureSightings = 'creature_sightings';
    case Emotes = 'emotes';
    case TalentChanges = 'talent_changes';
    case TalentSnapshots = 'talent_snapshots';
    case TalentRespecs = 'talent_respecs';
    case TalentGroupSpans = 'talent_group_spans';
    case CampStays = 'camp_stays';
    case CampBenefits = 'camp_benefits';

    /** `money_changes.src` of a balance carried over from the `S` line (core), against `M` money lines (economy). */
    public const string MONEY_LINE = 'M';

    /** `camp_stays.ended` when the campfire aura went while the session ran. */
    public const string CAMP_ENDED_AURA = 'w';

    /** `camp_stays.ended` when the session ended with the character still near the fire. */
    public const string CAMP_ENDED_SESSION = 's';

    /** The sharing group whose removal deletes these rows. */
    public function group(): string
    {
        return match ($this) {
            self::PlaySpans, self::LevelUps => SharingGroup::CORE,
            self::XpGains => SharingGroup::XP,
            self::Kills, self::Deaths, self::DungeonRuns, self::CampStays, self::CampBenefits => SharingGroup::COMBAT,
            self::QuestEvents => SharingGroup::QUESTS,
            self::MoneyChanges => SharingGroup::ECONOMY,
            self::LootOpens, self::LootDrops => SharingGroup::LOOT,
            self::SkillUps, self::Crafts => SharingGroup::PROFESSIONS,
            self::PathPoints, self::MovementStretches => SharingGroup::MOVEMENT,
            self::CreatureSightings => SharingGroup::WORLD,
            self::Emotes => SharingGroup::SOCIAL,
            self::TalentChanges, self::TalentSnapshots, self::TalentRespecs, self::TalentGroupSpans => SharingGroup::BUILD,
        };
    }

    /** Whether the table holds a row for every step or sighting, so its chunks cover a day and not a week. */
    public function dense(): bool
    {
        return $this === self::PathPoints || $this === self::CreatureSightings;
    }

    /**
     * Columns of this table that another sharing group fills, by that group: its removal nulls them while the rows
     * stay (docs/ROWS.md "Missing groups").
     *
     * @return array<string, list<string>>
     */
    public function groupColumns(): array
    {
        return match ($this) {
            self::PlaySpans => [SharingGroup::XP => ['afk_ms']],
            self::CampStays => [
                SharingGroup::XP => ['afk_seconds'],
                SharingGroup::PROFESSIONS => ['crafts'],
                SharingGroup::MOVEMENT => ['map', 'x', 'y', 'u', 'v'],
            ],
            default => [],
        };
    }

    /** @return list<self> */
    public static function inGroup(string $group): array
    {
        return array_values(array_filter(self::cases(), fn (self $table): bool => $table->group() === $group));
    }

    /**
     * The decoder's key => the column it fills, where they differ.
     *
     * @return array<string, string>
     */
    public function renames(): array
    {
        return match ($this) {
            self::PlaySpans => ['zone' => 'zone_name'],
            self::Kills => ['by' => 'credit'],
            self::DungeonRuns => ['index' => 'run_index'],
            self::SkillUps => ['rank' => 'skill_rank', 'max' => 'max_rank'],
            default => [],
        };
    }

    /**
     * The decoder's keys that no column keeps.
     *
     * @return list<string>
     */
    public function dropped(): array
    {
        return match ($this) {
            self::PlaySpans => ['end'],
            default => [],
        };
    }

    /**
     * The columns that hold a time in epoch milliseconds in the decoder's row.
     *
     * @return list<string>
     */
    public function instants(): array
    {
        return match ($this) {
            self::DungeonRuns => ['left_at'],
            default => [],
        };
    }

    /** A PostgreSQL timestamptz literal for epoch milliseconds. */
    public static function instant(int $milliseconds): string
    {
        // One row in a hundred thousand: no Carbon object per row.
        return gmdate('Y-m-d H:i:s', intdiv($milliseconds, 1000)).sprintf('.%03d+00', $milliseconds % 1000);
    }
}
