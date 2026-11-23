<?php

namespace App\View\Sc;

/** The game's colour of a class, used for a character's name, its dot and its level rule. */
final class ClassColor
{
    private const array COLORS = [
        'WARRIOR' => '#c69b6d', 'PALADIN' => '#f48cba', 'HUNTER' => '#aad372', 'ROGUE' => '#fff468', 'PRIEST' => '#ffffff', 'SHAMAN' => '#0070dd',
        'MAGE' => '#3fc7eb', 'WARLOCK' => '#8788ee', 'DRUID' => '#ff7c0a', 'DEATHKNIGHT' => '#c41e3a', 'DEMONHUNTER' => '#a330c9',
    ];

    public static function of(?string $class): string
    {
        return self::COLORS[strtoupper((string) $class)] ?? '#c3c7cf';
    }
}
