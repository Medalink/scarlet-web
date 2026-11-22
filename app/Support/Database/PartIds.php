<?php

namespace App\Support\Database;

use App\Models\Install;
use App\Models\Part;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/** Part ids are 32 bytes; the API and the code write them as 64 lower-case hex characters. */
final class PartIds
{
    /**
     * A PostgreSQL text[] literal of hex ids, bound as one value: `id in (select decode(x, 'hex') ...)`.
     *
     * @param  list<string>  $hex
     */
    public static function literal(array $hex): string
    {
        foreach ($hex as $id) {
            if (preg_match('/^[0-9a-f]{64}$/', $id) !== 1) {
                throw new InvalidArgumentException('A part id is 64 lower-case hex characters.');
            }
        }

        return '{'.implode(',', $hex).'}';
    }

    /**
     * @param  Builder<Part>  $query
     * @param  list<string>  $hex
     * @return Builder<Part>
     */
    public static function whereIn(Builder $query, array $hex): Builder
    {
        return $query->whereRaw("parts.id in (select decode(wanted, 'hex') from unnest(?::text[]) as wanted)", [self::literal($hex)]);
    }

    /**
     * @param  list<string>|null  $groups  null for every group
     * @param  Install|null  $install  only the parts this install claims
     * @return list<string> the hex ids of a character's parts in these groups
     */
    public static function held(int $characterId, ?array $groups = null, ?Install $install = null): array
    {
        $query = Part::query()->where('character_id', $characterId)->selectRaw("encode(id, 'hex') as hex");
        if ($groups !== null) {
            $query->whereIn('group_key', $groups);
        }
        if ($install !== null) {
            $query->claimedBy($install);
        }

        /** @var list<string> */
        return $query->toBase()->pluck('hex')->all();
    }
}
