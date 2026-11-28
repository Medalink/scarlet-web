<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

/** What a loot window opened on: the decoder's `loot_opens.source_kind` (docs/ROWS.md). */
enum LootSourceKind: string
{
    case Creature = 'c';
    case Object = 'g';
    case Bag = 'i';
    case Other = 'o';

    /** The page of a source of this kind: a creature, an object, or the item a container is. */
    public function page(int $id): ?string
    {
        return match ($this) {
            self::Creature => '/creatures/'.$id,
            self::Object => '/objects/'.$id,
            self::Bag => '/items/'.$id,
            self::Other => null,
        };
    }

    /** What a source is called: its dictionary name, else its kind and id (`Object 649094`). */
    public function title(?string $name, int $id): string
    {
        return $name ?? match ($this) {
            self::Creature => 'Creature '.$id,
            self::Object => 'Object '.$id,
            self::Bag => 'Item '.$id,
            self::Other => 'Source '.$id,
        };
    }

    /** A source with no name, told by its kind and the zone it is in (`Object in Searing Gorge`). */
    public function kindIn(string $zone): string
    {
        return match ($this) {
            self::Creature => 'Creature',
            self::Object => 'Object',
            self::Bag => 'Container',
            self::Other => 'Source',
        }.' in '.$zone;
    }

    /**
     * Adds `source`, the dictionary name of the row's creature, object or container, to a query over a loot aggregate
     * aliased `d`: null when the dictionary has no name for it.
     */
    public static function withName(Builder $query): Builder
    {
        return $query->selectRaw(
            '(select n.name from names n where n.kind = case d.source_kind when ? then ? when ? then ? when ? then ? end and n.id = d.source_id order by n.votes desc, n.name limit 1) as source',
            [self::Creature->value, NameKind::Npc->value, self::Object->value, NameKind::Object->value, self::Bag->value, NameKind::Item->value],
        );
    }
}
