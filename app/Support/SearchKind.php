<?php

namespace App\Support;

/** What search looks for. The value is the name in URLs and in the API (`kind=creatures`). */
enum SearchKind: string
{
    case Characters = 'characters';
    case Creatures = 'creatures';
    case Items = 'items';
    case Zones = 'zones';
    case Quests = 'quests';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** The first path segment of the result pages of this kind. */
    public function path(): string
    {
        return match ($this) {
            self::Characters => '/c/',
            self::Creatures => '/creatures/',
            self::Items => '/items/',
            self::Zones => '/zones/',
            self::Quests => '/quests/',
        };
    }

    /** The kind of the `names` dictionary this kind reads, or null when it has a table of its own. */
    public function nameKind(): ?NameKind
    {
        return match ($this) {
            self::Creatures => NameKind::Npc,
            self::Items => NameKind::Item,
            self::Quests => NameKind::Quest,
            default => null,
        };
    }
}
