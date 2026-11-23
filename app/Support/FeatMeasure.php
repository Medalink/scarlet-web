<?php

namespace App\Support;

/**
 * What a feat counts; each counter of App\Actions\Feats\Counts fills the feats of its measures. The value is the
 * measure's name in the catalog (`docs/DESIGN.md`, the feats tables).
 */
enum FeatMeasure: string
{
    case Casts = 'casts';
    case Hours = 'hours';
    case Kills = 'kills';
    case Deaths = 'deaths';
    case PlayerKills = 'player-kills';
    case DungeonRuns = 'dungeon-runs';
    case DungeonBosses = 'dungeon-bosses';
    case RaidBosses = 'raid-bosses';
    case DeathlessRuns = 'deathless-runs';
    case Damage = 'damage';
    case Healing = 'healing';
    case DamageTaken = 'damage-taken';
    case BestDamage = 'best-damage';
    case BestHealing = 'best-healing';
    case BestDamageTaken = 'best-damage-taken';
}
