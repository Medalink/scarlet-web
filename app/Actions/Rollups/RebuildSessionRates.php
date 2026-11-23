<?php

namespace App\Actions\Rollups;

use Illuminate\Support\Facades\DB;

/** Rewrites `session_rates_bracket`: the percentiles of XP, kills and quests an hour over the sessions of each ruleset and ten-level bracket. */
class RebuildSessionRates
{
    /** The percentile of each of the 19 cuts, 5 to 95 in steps of five. */
    public const array STEPS = [0.05, 0.10, 0.15, 0.20, 0.25, 0.30, 0.35, 0.40, 0.45, 0.50, 0.55, 0.60, 0.65, 0.70, 0.75, 0.80, 0.85, 0.90, 0.95];

    public const int MIN_PLAYED_MS = 600_000;

    private const array METRICS = ['xp_h' => 'xp_total', 'kills_h' => 'kills', 'quests_h' => 'quests_done'];

    /** @return int rows written */
    public function handle(): int
    {
        $parts = [];
        foreach (self::METRICS as $metric => $column) {
            $parts[] = "select ruleset_id, (level_start / 10) * 10 as bracket, '{$metric}' as metric, {$column} / (played_ms / 3600000.0) as value "
                .'from sessions where counted and played_ms >= '.self::MIN_PLAYED_MS." and level_start is not null and {$column} is not null";
        }
        $steps = implode(', ', self::STEPS);

        return DB::transaction(function () use ($parts, $steps): int {
            DB::table('session_rates_bracket')->delete();

            return DB::affectingStatement(
                'insert into session_rates_bracket (ruleset_id, bracket, metric, n, cuts, updated_at) '
                ."select ruleset_id, bracket, metric, count(*), to_jsonb(percentile_cont(array[{$steps}]) within group (order by value)), now() "
                .'from ('.implode(' union all ', $parts).') s group by ruleset_id, bracket, metric',
            );
        });
    }
}
