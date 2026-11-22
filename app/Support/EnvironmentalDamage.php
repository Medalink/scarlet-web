<?php

namespace App\Support;

/** What the world did to a character without a unit: the decoder's `environmental_type` of a death and of its hits. */
enum EnvironmentalDamage: string
{
    case Falling = 'Falling';
    case Drowning = 'Drowning';
    case Fatigue = 'Fatigue';
    case Fire = 'Fire';
    case Lava = 'Lava';
    case Slime = 'Slime';

    public function label(): string
    {
        return match ($this) {
            self::Falling => 'Fall',
            default => $this->value,
        };
    }
}
