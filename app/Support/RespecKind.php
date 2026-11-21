<?php

namespace App\Support;

/** `talent_respecs.kind` (docs/ROWS.md), and `Between`, a respec read off two builds with no respec recorded between them. */
enum RespecKind: string
{
    case Reset = 'reset';
    case Spec = 'spec';
    case Refund = 'refund';
    case Between = 'between';

    public function label(): string
    {
        return match ($this) {
            self::Reset => 'Talents reset',
            self::Spec => 'Spec changed',
            self::Refund => 'Points refunded',
            self::Between => 'Between sessions',
        };
    }
}
