<?php

namespace App\Support;

/** The combat event of a hit in a death recap: the decoder's `death_hits.event`. */
enum HitEvent: string
{
    case Swing = 'SWING_DAMAGE';
    case Ranged = 'RANGE_DAMAGE';
    case Spell = 'SPELL_DAMAGE';
    case Periodic = 'SPELL_PERIODIC_DAMAGE';
    case Environmental = 'ENVIRONMENTAL_DAMAGE';

    public function label(): string
    {
        return match ($this) {
            self::Swing => 'Melee swing',
            self::Ranged => 'Ranged shot',
            self::Spell => 'Spell',
            self::Periodic => 'Damage over time',
            self::Environmental => 'Environment',
        };
    }
}
