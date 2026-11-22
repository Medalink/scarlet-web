<?php

namespace App\Support;

/** The mode of a movement stretch (`W`), and the colour it fills a bar and a key with. */
enum MovementMode: string
{
    case Run = 'f';
    case Walk = 'w';
    case Mount = 'm';
    case Swim = 's';
    case Fly = 'a';

    public function label(): string
    {
        return match ($this) {
            self::Run => 'Running',
            self::Walk => 'Walking',
            self::Mount => 'Mounted',
            self::Swim => 'Swimming',
            self::Fly => 'Flying',
        };
    }

    /** The class that fills a bar segment or a key swatch, the item qualities' palette. */
    public function fill(): string
    {
        return match ($this) {
            self::Run => 'c-q2',
            self::Walk => 'c-q1',
            self::Mount => 'c-q0',
            self::Swim => 'c-q3',
            self::Fly => 'c-q4',
        };
    }
}
