<?php

namespace App\View\Pages;

use App\Support\RateSample;
use App\View\Format;

/** The Kills an hour by group size module of the loot rules and group size page (docs/comps/groups.html). */
final class GroupKillRates
{
    /**
     * @param  list<array{size: int, kills: int, seconds: int}>  $rows  the answer of GroupKills::bySize
     * @return array<string, mixed>
     */
    public static function bySize(array $rows, bool $narrowed): array
    {
        return [
            'empty' => $rows === [] ? 'No kill in a group of two or more is recorded yet.' : null,
            'columns' => [['label' => 'Size', 'numeric' => true], ['label' => 'Kills', 'numeric' => true], ['label' => 'Hours', 'numeric' => true], ['label' => 'Kills an hour', 'numeric' => true]],
            'rows' => array_map(fn (array $row): array => ['cells' => [(string) $row['size'], Format::number($row['kills']), Format::number($row['seconds'] / 3600, 1), self::perHour($row['kills'], $row['seconds'])]], $rows),
            'note' => 'Time alone is not counted.'.($narrowed ? ' Covers every creature, not only this source.' : ''),
        ];
    }

    public static function perHour(int $kills, int $seconds): string
    {
        return $kills >= RateSample::HOLLOW_FROM && $seconds > 0 ? Format::number($kills / ($seconds / 3600), 1) : '–';
    }
}
