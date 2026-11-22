<?php

namespace App\Support;

/** The continuous aggregates (docs/DESIGN.md, Rollups). The value is the view name. */
enum Rollup: string
{
    case XpDailyCharacter = 'xp_daily_character';
    case XpDailyRuleset = 'xp_daily_ruleset';
    case PlayHourlyCharacter = 'play_hourly_character';
    case PlayHourlyRuleset = 'play_hourly_ruleset';
    case LevelTimeRuleset = 'level_time_ruleset';
    case KillsDailyCharacter = 'kills_daily_character';
    case KillsDailyRuleset = 'kills_daily_ruleset';
    case DeathsDailyCharacter = 'deaths_daily_character';
    case DeathsDailyRuleset = 'deaths_daily_ruleset';
    case DungeonsDailyRuleset = 'dungeons_daily_ruleset';
    case QuestsDailyRuleset = 'quests_daily_ruleset';
    case MoneyDailyCharacter = 'money_daily_character';
    case MoneyDailyRuleset = 'money_daily_ruleset';
    case DropsDaily = 'drops_daily';
    case OpensDaily = 'opens_daily';
    case SkillsDailyRuleset = 'skills_daily_ruleset';
    case MovementDailyCharacter = 'movement_daily_character';
    case MovementDailyRuleset = 'movement_daily_ruleset';
    case EmotesDailyCharacter = 'emotes_daily_character';
    case EmotesDailyRuleset = 'emotes_daily_ruleset';
    case HeatDailyCharacter = 'heat_daily_character';
    case HeatDailyRuleset = 'heat_daily_ruleset';
    case DeathHeatDailyCharacter = 'death_heat_daily_character';
    case DeathHeatDailyRuleset = 'death_heat_daily_ruleset';
    case WorldHeatDailyCharacter = 'world_heat_daily_character';
    case WorldHeatDailyRuleset = 'world_heat_daily_ruleset';
    case WorldHeatFineDailyCharacter = 'world_heat_fine_daily_character';
    case WorldHeatFineDailyRuleset = 'world_heat_fine_daily_ruleset';
    case WorldDeathHeatDailyCharacter = 'world_death_heat_daily_character';
    case WorldDeathHeatDailyRuleset = 'world_death_heat_daily_ruleset';
    case WorldSightingHeatDailyRuleset = 'world_sighting_heat_daily_ruleset';
    case CreatureFactsDaily = 'creature_facts_daily';
    case CampStaysHourlyRuleset = 'camp_stays_hourly_ruleset';
    case CampHeatDailyRuleset = 'camp_heat_daily_ruleset';
    case CampBenefitsDailyRuleset = 'camp_benefits_daily_ruleset';
    case CampStaysDailyCharacter = 'camp_stays_daily_character';
}
