<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

/**
 * What the loot pages list: the windows read for loot, skinning, herbs, ore or a container, without the bobber of a
 * fishing line, whose catches are on the fishing page (docs/LOOT.md, Views). Every read of the loot explorer goes
 * through here, so no table or figure counts a fishing window.
 */
final class LootListing
{
    /**
     * The reads an object has a page for.
     *
     * @return list<LootRead>
     */
    public static function objectReads(): array
    {
        return [LootRead::Loot, LootRead::Herb, LootRead::Ore];
    }

    /** The windows of an object, read for loot, herbs or ore: the bobber is out. */
    public static function ofObjects(Builder $query, string $read = 'read', string $kind = 'source_kind', string $id = 'source_id'): Builder
    {
        return self::withoutBobber($query->whereIn($read, array_map(fn (LootRead $each): string => $each->value, self::objectReads())), $kind, $id);
    }

    /** The windows of every kind of source, read for anything but fishing. */
    public static function ofSources(Builder $query, string $read = 'read', string $kind = 'source_kind', string $id = 'source_id'): Builder
    {
        $listed = [LootRead::Loot, LootRead::Skin, LootRead::Herb, LootRead::Ore, LootRead::Bag];

        return self::withoutBobber($query->whereIn($read, array_map(fn (LootRead $each): string => $each->value, $listed)), $kind, $id);
    }

    private static function withoutBobber(Builder $query, string $kind, string $id): Builder
    {
        return $query->where(fn (Builder $either): Builder => $either
            ->where($kind, '<>', LootSourceKind::Object->value)
            ->orWhere($id, '<>', GameObjects::FISHING_BOBBER));
    }
}
