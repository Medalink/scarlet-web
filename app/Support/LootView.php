<?php

namespace App\Support;

/** The table `/loot` shows (`?view=`): the creatures, objects or containers that were looted, or the items that dropped. */
enum LootView: string
{
    case Creatures = 'creatures';
    case Objects = 'objects';
    case Containers = 'containers';
    case Items = 'items';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** What a window of the view's source is called in its count column. */
    public function opened(): string
    {
        return $this === self::Creatures ? 'Corpses looted' : 'Opened';
    }

    /** The kind of source whose windows the view lists; null for the items. */
    public function sourceKind(): ?LootSourceKind
    {
        return match ($this) {
            self::Creatures => LootSourceKind::Creature,
            self::Objects => LootSourceKind::Object,
            self::Containers => LootSourceKind::Bag,
            self::Items => null,
        };
    }

    /** The `names` kind a source of the view is searched by. */
    public function nameKind(): NameKind
    {
        return match ($this) {
            self::Creatures => NameKind::Npc,
            self::Objects => NameKind::Object,
            self::Containers, self::Items => NameKind::Item,
        };
    }
}
