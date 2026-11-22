<?php

namespace App\Support;

/** The loot rule in force for a window: the decoder's `loot_opens.loot_rule` (docs/ROWS.md). */
enum LootRule: string
{
    case Solo = 'solo';
    case Group = 'group';
    case FreeForAll = 'freeforall';
    case RoundRobin = 'roundrobin';
    case NeedBeforeGreed = 'needbeforegreed';
    case MasterLooter = 'masterlooter';
    case Personal = 'personal';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Solo => 'Solo',
            self::Group => 'Group loot',
            self::FreeForAll => 'Free for all',
            self::RoundRobin => 'Round robin',
            self::NeedBeforeGreed => 'Need before greed',
            self::MasterLooter => 'Master looter',
            self::Personal => 'Personal loot',
            self::Unknown => 'Unknown',
        };
    }

    /** A rule's words for a stored value; a value this enum does not know is shown as it is. */
    public static function words(string $value): string
    {
        return self::tryFrom($value)?->label() ?? $value;
    }
}
