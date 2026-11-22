<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/** The queries behind every kills an hour figure (docs/LOOT.md, Metrics: Kills an hour by size): member time in a group, and the member kills that fall in it. */
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
     * The kills, as `k`, that the character got credit for (not a player), joined on character, session and `group_at` to
     * the span they fell in, as `s`. `$spans` selects `character_id`, `session_start` and `at` of the spans, and whatever
     * else the caller groups by.
     */
    public static function killed(Builder $spans): Builder
    {
        return DB::table('kills as k')->joinSub($spans, 's', fn (JoinClause $join) => $join
            ->on('k.character_id', '=', 's.character_id')->on('k.session_start', '=', 's.session_start')->on('k.group_at', '=', 's.at'))
            ->where('k.counted', true)->where('k.credit', 1)->where('k.player', false);
    }
}
