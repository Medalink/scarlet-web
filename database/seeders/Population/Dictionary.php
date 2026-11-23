<?php

namespace Database\Seeders\Population;

use Illuminate\Support\Facades\DB;

/**
 * Which spells, items and creatures the shared name dictionary already names. The recordings name an id only when
 * nothing does, so thousands of seeded sessions never outvote a name a real install reported.
 */
final class Dictionary
{
    /** @var array<string, array<int, true>> */
    private static array $taken = [];

    public static function offer(string $kind, int $id): bool
    {
        self::$taken[$kind] ??= array_fill_keys(DB::table('names')->where('kind', $kind)->pluck('id')->all(), true);

        if (isset(self::$taken[$kind][$id])) {
            return false;
        }
        self::$taken[$kind][$id] = true;

        return true;
    }
}
