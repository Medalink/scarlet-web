<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The part ids the last manifest of an install asked for, per character. /upload/parts refuses
 * a part that is not in the list. The list lives a day in the cache; an install that lost it
 * sends its manifest again.
 */
final class WantedParts
{
    private const int HOURS = 24;

    /** @param  list<string>  $ids  lower-case hex */
    public static function remember(string $installId, int $characterId, array $ids): void
    {
        Cache::put(self::key($installId, $characterId), array_flip($ids), now()->addHours(self::HOURS));
    }

    /** @return array<string, int> the ids as keys */
    public static function ids(string $installId, int $characterId): array
    {
        /** @var array<string, int> */
        return Cache::get(self::key($installId, $characterId), []);
    }

    private static function key(string $installId, int $characterId): string
    {
        return 'upload-wanted:'.$installId.':'.$characterId;
    }
}
