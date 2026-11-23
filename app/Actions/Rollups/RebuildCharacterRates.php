<?php

namespace App\Actions\Rollups;

use App\Support\MovementMode;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites `character_rates_bracket`: the percentiles of kills, XP, deaths, quests, miles and jumps an hour over the characters of each ruleset, class and
 * ten-level bracket. A character counts in every bracket it played in, over the counted sessions that began there, and only with {@see self::MIN_PLAYED_MS} played in it.
 */
class RebuildCharacterRates
{
    public const string KILLS = 'kills_h';

    public const string XP = 'xp_h';

    public const string DEATHS = 'deaths_h';

    public const string QUESTS = 'quests_h';

    public const string MILES = 'miles_h';

    public const string JUMPS = 'jumps_h';

    public const int MIN_PLAYED_MS = 1_800_000;

    /** @return int rows written */
    public function handle(): int
    {
        $steps = implode(', ', RebuildSessionRates::STEPS);
        $travel = implode(', ', array_map(fn (MovementMode $mode): string => "'{$mode->value}'", [MovementMode::Run, MovementMode::Walk, MovementMode::Mount, MovementMode::Swim]));
        $values = sprintf(
            "(('%s'), kills / hours), (('%s'), xp / hours), (('%s'), deaths / hours), (('%s'), quests / hours), (('%s'), miles / hours), (('%s'), jumps / hours)",
            self::KILLS, self::XP, self::DEATHS, self::QUESTS, self::MILES, self::JUMPS,
        );

        return DB::transaction(function () use ($steps, $travel, $values): int {
            DB::table('character_rates_bracket')->delete();

            return DB::affectingStatement(
                "with moved as (select character_id, session_start, sum(case when mode in ({$travel}) then yards else 0 end) as yards, sum(jumps) as jumps "
                .'from movement_stretches where counted group by character_id, session_start), '
                .'per as (select s.ruleset_id, s.class, (s.level_start / 10) * 10 as bracket, s.character_id, sum(s.played_ms) / 3600000.0 as hours, '
                .'sum(s.kills) as kills, sum(s.xp_total) as xp, sum(s.deaths) as deaths, sum(s.quests_done) as quests, '
                .'sum(coalesce(m.yards, 0)) / 1760.0 as miles, sum(coalesce(m.jumps, 0)) as jumps '
                .'from sessions s left join moved m on m.character_id = s.character_id and m.session_start = s.session_start '
                .'where s.counted and s.level_start is not null and s.class is not null '
                .'group by s.ruleset_id, s.class, (s.level_start / 10) * 10, s.character_id having sum(s.played_ms) >= '.self::MIN_PLAYED_MS.') '
                .'insert into character_rates_bracket (ruleset_id, class, bracket, metric, n, cuts, updated_at) '
                ."select ruleset_id, class, bracket, metric, count(*), to_jsonb(percentile_cont(array[{$steps}]) within group (order by value)), now() "
                ."from per cross join lateral (values {$values}) as v(metric, value) where value is not null group by ruleset_id, class, bracket, metric",
            );
        });
    }
}
