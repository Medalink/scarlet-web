<?php

namespace App\Support;

/** The day a loot aggregate row belongs to, as SQL, so the windows of one day can be told from another's (`LootArm::overDays`). */
final class LootDay
{
    /**
     * The UTC date of an aggregate's `bucket`, as `2026-11-16`.
     *
     * @param  literal-string  $bucket
     * @return literal-string
     */
    public static function of(string $bucket = 'bucket'): string
    {
        return "to_char({$bucket} at time zone 'UTC', 'YYYY-MM-DD')";
    }

    /**
     * The same for the rows of free-for-all loot, and `''` for every other rule: only a free-for-all arm counts by day.
     *
     * @param  literal-string  $rule
     * @param  literal-string  $bucket
     * @return literal-string
     */
    public static function ofFreeForAll(string $rule = 'loot_rule', string $bucket = 'bucket'): string
    {
        return "case when {$rule} = '".LootRule::FreeForAll->value."' then ".self::of($bucket)." else '' end";
    }
}
