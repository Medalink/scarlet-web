<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

/**
 * What a loot window was read for: the decoder's `loot_opens.read` and `loot_drops.read` (docs/ROWS.md). A drop table
 * counts `Loot` windows and a skinning table `Skin` windows. A window from a session not decoded at version 10, or
 * with no `combat` part, has no `read`: it is not a loot window and not a skin window, so every table leaves it out
 * (docs/LOOT.md, Metrics).
 */
enum LootRead: string
{
    case Loot = 'loot';
    case Skin = 'skin';
    case Herb = 'herb';
    case Ore = 'ore';
    case Fish = 'fish';
    case Bag = 'bag';
    case Other = 'other';

    /** How the item page says where a window was read. */
    public function label(): string
    {
        return match ($this) {
            self::Loot => 'Loot',
            self::Skin => 'Skinning',
            self::Herb => 'Herbalism',
            self::Ore => 'Mining',
            self::Fish => 'Fishing',
            self::Bag => 'Container',
            self::Other => 'Other',
        };
    }

    /** The label of a stored value; a value this enum does not know is shown as it is. */
    public static function words(string $value): string
    {
        return self::tryFrom($value)?->label() ?? $value;
    }

    /** The rows read for this and nothing else. */
    public function within(Builder $query, string $column = 'read'): Builder
    {
        return $query->where($column, $this->value);
    }

    /** The rows whose read is known, whatever it is. */
    public static function known(Builder $query, string $column = 'read'): Builder
    {
        return $query->whereNotNull($column);
    }
}
