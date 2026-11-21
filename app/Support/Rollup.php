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
    case HeatDailyCharacter = 'heat_daily_character';
    case HeatDailyRuleset = 'heat_daily_ruleset';
    case DeathHeatDailyCharacter = 'death_heat_daily_character';
    case DeathHeatDailyRuleset = 'death_heat_daily_ruleset';
}
