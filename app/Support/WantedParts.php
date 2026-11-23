<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The part ids the last manifest of an install asked for, per character. /upload/parts refuses
 * a part that is not in the list. The list lives a day in the cache as the ids packed into one
 * binary string, at most scarlet.upload.max_wanted_ids of them; an install that lost it sends
 * its manifest again.
 */
final class WantedParts
{
    private const int HOURS = 24;

    private const int ID_BYTES = 32;

    /** @param  list<string>  $ids  lower-case hex */
    public static function remember(string $installId, int $characterId, array $ids): void
    {
        $ids = array_slice($ids, 0, max(0, (int) config('scarlet.upload.max_wanted_ids')));
        Cache::put(self::key($installId, $characterId), hex2bin(implode('', $ids)), now()->addHours(self::HOURS));
    }

    /** @return array<string, int> the ids as keys */
    public static function ids(string $installId, int $characterId): array
    {
        $stored = Cache::get(self::key($installId, $characterId));
        if (is_string($stored)) {
            return self::unpack($stored);
        }

        /** @var array<string, int> */
        return is_array($stored) ? $stored : [];
    }

    /** @return array<string, int> the ids as keys */
    private static function unpack(string $packed): array
    {
        if ($packed === '') {
            return [];
        }

        $ids = [];
        foreach (str_split($packed, self::ID_BYTES) as $id) {
            $ids[bin2hex($id)] = 1;
        }

        return $ids;
    }

    private static function key(string $installId, int $characterId): string
    {
        return 'upload-wanted:'.$installId.':'.$characterId;
    }
}
