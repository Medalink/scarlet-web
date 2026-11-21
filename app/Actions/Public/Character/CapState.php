<?php

namespace App\Actions\Public\Character;

use Illuminate\Support\Facades\DB;

class CapState
{
    /** Played seconds at its level before a character that stopped gaining experience counts as at the cap. */
    public const int MIN_SECONDS = 3600;

    /** Kills at its level that show the character still plays there. */
    public const int MIN_KILLS = 10;

    /**
     * Whether a character is at the level cap, read from its data and never from a level number: it has played
     * MIN_SECONDS at its current level, killed MIN_KILLS there and gained no experience there, in a ruleset that has
     * experience on. Null when it is not at the cap.
     *
     * The time at the level is what Scarlet recorded there. When Scarlet saw the level-up into the level, the
     * client's played total since that level-up counts too, if it is larger. `observed` is true when the character was
     * first seen at the level, so the time is counted from its first session and never reaches back to when it arrived.
     *
     * @return array{level: int, seconds_at_cap: int, observed: bool}|null
     */
    public function handle(int $characterId): ?array
    {
        $session = DB::table('sessions')->where('character_id', $characterId)->orderByDesc('end_at')->first(['ruleset_id', 'level_end']);
        $character = DB::table('characters')->where('id', $characterId)->first(['level', 'played_seconds', 'played_known']);
        if ($session === null || $character === null) {
            return null;
        }
        $level = $character->level ?? $session->level_end;
        if ($level === null) {
            return null;
        }
        $level = (int) $level;
        if (DB::table('rulesets')->where('id', $session->ruleset_id)->where('xp_off', true)->exists()) {
            return null;
        }
        $recorded = (int) round((float) DB::table('play_spans')->where('character_id', $characterId)->where('counted', true)->where('level', $level)->sum('ms') / 1000);
        $arrived = DB::table('character_levels')->where('character_id', $characterId)->where('counted', true)->where('level', $level)->value('before_seconds');
        $seconds = $arrived !== null && $character->played_known ? max($recorded, (int) $character->played_seconds - (int) $arrived) : $recorded;
        $gained = DB::table('xp_gains')->where('character_id', $characterId)->where('counted', true)->where('level', $level)->where('amount', '>', 0)->exists();
        if ($seconds < self::MIN_SECONDS || $gained) {
            return null;
        }
        $killed = DB::table('kills')->where('character_id', $characterId)->where('counted', true)->where('level', $level)->offset(self::MIN_KILLS - 1)->limit(1)->exists();

        return $killed ? ['level' => $level, 'seconds_at_cap' => $seconds, 'observed' => $arrived === null] : null;
    }
}
