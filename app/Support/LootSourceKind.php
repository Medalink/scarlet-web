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
