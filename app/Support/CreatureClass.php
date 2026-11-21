<?php

namespace App\Support;

enum CreatureClass: string
{
    case Normal = 'normal';
    case Elite = 'elite';
    case Rare = 'rare';
    case RareElite = 'rareelite';
    case WorldBoss = 'worldboss';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'normal',
            self::Elite => 'elite',
            self::Rare => 'rare',
            self::RareElite => 'rare elite',
            self::WorldBoss => 'world boss',
        };
    }
}
