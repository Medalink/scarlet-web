<?php

namespace App\Support;

use App\Models\Ruleset;

/** The server filter of the pages: the war mode of the ruleset. */
enum Server: string
{
    case All = 'all';
    case Pve = 'pve';
    case Pvp = 'pvp';

    public function label(): string
    {
        return match ($this) {
            self::All => 'PvE and PvP',
            self::Pve => 'PvE',
            self::Pvp => 'PvP',
        };
    }

    /** The `rulesets.war_mode` this filter keeps, or null for every ruleset. */
    public function warMode(): ?string
    {
        return match ($this) {
            self::All => null,
            self::Pve => Ruleset::WAR_MODE_OFF,
            self::Pvp => Ruleset::WAR_MODE_ON,
        };
    }
}
