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
    case DeathHits = 'death_hits';
    case DungeonRuns = 'dungeon_runs';
    case QuestEvents = 'quest_events';
    case MoneyChanges = 'money_changes';
    case LootOpens = 'loot_opens';
    case LootDrops = 'loot_drops';
    case BossLoot = 'boss_loot';
    case SkillUps = 'skill_ups';
    case Crafts = 'crafts';
    case PathPoints = 'path_points';
    case MovementStretches = 'movement_stretches';
    case CreatureSightings = 'creature_sightings';
    case Emotes = 'emotes';
    case AuctionPrices = 'auction_prices';
    case TalentChanges = 'talent_changes';
    case TalentSnapshots = 'talent_snapshots';
    case TalentRespecs = 'talent_respecs';
    case TalentGroupSpans = 'talent_group_spans';
    case CampStays = 'camp_stays';
    case CampBenefits = 'camp_benefits';
    case Gathers = 'gathers';
    case GatherItems = 'gather_items';
    case HardcoreDeaths = 'hardcore_deaths';
    case WhoResults = 'who_results';
    case InspectedGear = 'inspected_gear';
    case BattlegroundMatches = 'battleground_matches';
    case BattlegroundScores = 'battleground_scores';
    case QuestBaselines = 'quest_baselines';
    case TitleBaselines = 'title_baselines';
    case ExploredBaselines = 'explored_baselines';
    case InstanceLocks = 'instance_locks';
    case GroupSpans = 'group_spans';
    case GroupLoot = 'group_loot';
    case LootRolls = 'loot_rolls';
    case PeerOpens = 'peer_opens';
    case PeerDrops = 'peer_drops';

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
            self::Kills, self::Deaths, self::DeathHits, self::DungeonRuns, self::CampStays, self::CampBenefits, self::InstanceLocks => SharingGroup::COMBAT,
            self::QuestEvents, self::QuestBaselines => SharingGroup::QUESTS,
            self::TitleBaselines => SharingGroup::COLLECTIONS,
            self::ExploredBaselines => SharingGroup::WORLD,
            self::MoneyChanges, self::AuctionPrices => SharingGroup::ECONOMY,
            self::LootOpens, self::LootDrops, self::BossLoot, self::GroupLoot, self::LootRolls, self::PeerOpens, self::PeerDrops => SharingGroup::LOOT,
            self::SkillUps, self::Crafts, self::Gathers, self::GatherItems => SharingGroup::PROFESSIONS,
            self::PathPoints, self::MovementStretches => SharingGroup::MOVEMENT,
            self::CreatureSightings => SharingGroup::WORLD,
            self::Emotes, self::HardcoreDeaths, self::WhoResults, self::InspectedGear, self::BattlegroundScores, self::GroupSpans => SharingGroup::SOCIAL,
            self::BattlegroundMatches => SharingGroup::PVP,
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
            self::CampBenefits => [SharingGroup::XP => ['xp']],
            self::Kills => [
                SharingGroup::LOOT => ['loot_rule', 'group_size'],
                SharingGroup::SOCIAL => ['group_at'],
            ],
            self::LootOpens => [
                SharingGroup::COMBAT => ['read'],
                SharingGroup::MOVEMENT => ['map', 'x', 'y', 'u', 'v'],
                SharingGroup::SOCIAL => ['group_at'],
            ],
            self::LootDrops => [SharingGroup::COMBAT => ['read']],
            self::GroupSpans => [SharingGroup::PROFESSIONS => ['skinners']],
            self::PeerOpens => [SharingGroup::SOCIAL => ['group_at']],
            self::GroupLoot, self::LootRolls => [SharingGroup::SOCIAL => ['class_file']],
            self::Gathers => [
                SharingGroup::COMBAT => ['in_combat'],
                SharingGroup::LOOT => ['looted', 'source_id', 'again'],
                SharingGroup::BUILD => ['pole_item'],
                SharingGroup::MOVEMENT => ['map', 'x', 'y', 'u', 'v'],
            ],
            self::GatherItems => [SharingGroup::MOVEMENT => ['map']],
            default => [],
        };
    }

    /**
     * Groups besides its own that every row of the table is read from: removing one deletes the rows.
     *
     * @return list<string>
     */
    public function alsoNeeds(): array
    {
        return $this === self::GatherItems ? [SharingGroup::LOOT] : [];
    }

    /**
     * The tables whose rows go when the group is removed.
     *
     * @return list<self>
     */
    public static function inGroup(string $group): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $table): bool => $table->group() === $group || in_array($group, $table->alsoNeeds(), true),
        ));
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
            self::GroupSpans => ['end' => 'ended_at'],
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
            self::BattlegroundScores => ['match_at'],
            self::GroupSpans => ['ended_at'],
            self::Kills, self::LootOpens, self::PeerOpens => ['group_at'],
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
