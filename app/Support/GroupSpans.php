<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/** The queries behind the group figures of docs/LOOT.md: member time in a group, and the rows of a fact table that fall in it. */
final class GroupSpans
{
    /**
     * The `group_spans` rows, as `s`, of groups of 2 or more in a session that holds a kill. A session with no combat part
     * has no kills, so its time is left out of the hours that kills are divided by.
     */
    public static function timed(): Builder
    {
        return DB::table('group_spans as s')->where('s.counted', true)->where('s.size', '>=', 2)
            ->whereExists(fn (Builder $kill): Builder => $kill->selectRaw('1')->from('kills as t')->whereColumn('t.character_id', 's.character_id')->whereColumn('t.session_start', 's.session_start'));
    }

    /**
     * The rows of a fact table with a `group_at` column (`kills`, `loot_opens`), as `$alias`, each joined on character,
     * session and `group_at` to the span it fell in, as `s`. `$spans` selects `character_id`, `session_start` and `at` of
     * the spans, and whatever else the caller reads of them.
     */
    public static function onto(string $fact, string $alias, Builder $spans): Builder
    {
        return DB::table($fact.' as '.$alias)->joinSub($spans, 's', fn (JoinClause $join) => $join
            ->on($alias.'.character_id', '=', 's.character_id')->on($alias.'.session_start', '=', 's.session_start')->on($alias.'.group_at', '=', 's.at'));
    }

    /** The kills, as `k`, that the character got credit for and that were not a player, joined to their spans as `s`. */
    public static function killed(Builder $spans): Builder
    {
        return self::onto('kills', 'k', $spans)->where('k.counted', true)->where('k.credit', 1)->where('k.player', false);
    }
}
