<?php

namespace App\Support\Recap;

use App\Support\MovementMode;
use Illuminate\Support\Facades\DB;

/** `bests` and `own` of a recap: this session against the character's 30 sessions before it that ran 10 minutes or more. */
final class History
{
    private const int SESSIONS = 30;

    private const int MIN_PLAYED_MS = 600_000;

    private const int NEEDED = 5;

    private const float MILE = 1760.0;

    /**
     * @param  array<string, int|float>  $now  this session's totals: played, xp, kills, quests, earned, distance, deaths, miles
     * @return array{bests: list<string>, own: array{n: int, metrics: array<string, array<string, int|float>>}|null}
     */
    public static function read(Scope $scope, array $now): array
    {
        $earlier = self::earlier($scope);
        if (count($earlier) < self::NEEDED) {
            return ['bests' => [], 'own' => null];
        }

        $bests = [];
        foreach (self::bestsOf($now) as $key => $you) {
            $top = max(array_map(fn (array $totals): float => self::bestsOf($totals)[$key], $earlier));
            if ($you > 0 && $you > $top) {
                $bests[] = $key;
            }
        }
        $metrics = [];
        foreach (self::metricsOf($now) as $key => $you) {
            $values = array_map(fn (array $totals): float => self::metricsOf($totals)[$key], $earlier);
            sort($values);
            $metrics[$key] = [
                'p10' => self::percentile($values, 0.10),
                'p25' => self::percentile($values, 0.25),
                'p50' => self::percentile($values, 0.50),
                'p75' => self::percentile($values, 0.75),
                'p90' => self::percentile($values, 0.90),
                'you' => round($you, 2),
            ];
        }

        return ['bests' => $bests, 'own' => ['n' => count($earlier), 'metrics' => $metrics]];
    }

    /**
     * @param  array<string, int|float>  $totals
     * @return array<string, int|float>
     */
    public static function totals(array $totals, float $yards): array
    {
        return [...$totals, 'miles' => $yards / self::MILE];
    }

    /** @return list<array<string, int|float>> */
    private static function earlier(Scope $scope): array
    {
        $rows = DB::table('sessions')->where('character_id', $scope->character)->where('session_start', '<', $scope->start)->where('played_ms', '>=', self::MIN_PLAYED_MS)
            ->orderByDesc('session_start')->limit(self::SESSIONS)
            ->get(['session_start', 'played_ms', 'xp_total', 'kills', 'quests_done', 'copper_earned', 'distance', 'deaths']);
        $yards = DB::table('movement_stretches')->where('character_id', $scope->character)->whereIn('session_start', $rows->pluck('session_start'))->whereIn('mode', [MovementMode::Run->value, MovementMode::Walk->value, MovementMode::Mount->value, MovementMode::Swim->value])
            ->selectRaw('session_start, sum(yards) as yards')->groupBy('session_start')->pluck('yards', 'session_start');

        return array_values($rows->map(fn (object $row): array => self::totals([
            'played' => intdiv((int) $row->played_ms, 1000),
            'xp' => (int) $row->xp_total,
            'kills' => (int) $row->kills,
            'quests' => (int) $row->quests_done,
            'earned' => (int) $row->copper_earned,
            'distance' => (int) round((float) $row->distance),
            'deaths' => (int) $row->deaths,
        ], (float) ($yards[$row->session_start] ?? 0)))->all());
    }

    /**
     * @param  array<string, int|float>  $t
     * @return array<string, float>
     */
    private static function bestsOf(array $t): array
    {
        return ['xp' => (float) $t['xp'], 'kills' => (float) $t['kills'], 'quests' => (float) $t['quests'], 'earned' => (float) $t['earned'], 'distance' => (float) $t['distance'], 'xp_h' => self::hourly($t, 'xp')];
    }

    /**
     * @param  array<string, int|float>  $t
     * @return array<string, float>
     */
    private static function metricsOf(array $t): array
    {
        $hours = max(1, (int) $t['played']) / 3600;

        return ['xp_h' => self::hourly($t, 'xp'), 'kills_h' => self::hourly($t, 'kills'), 'quests_h' => self::hourly($t, 'quests'), 'miles_h' => $t['miles'] / $hours, 'deaths' => (float) $t['deaths'], 'played' => (float) $t['played']];
    }

    /** @param  array<string, int|float>  $t */
    private static function hourly(array $t, string $key): float
    {
        return $t['played'] > 0 ? $t[$key] / ($t['played'] / 3600) : 0.0;
    }

    /** @param  list<float>  $sorted */
    private static function percentile(array $sorted, float $p): float
    {
        $at = $p * (count($sorted) - 1);
        $lo = (int) floor($at);
        $hi = (int) ceil($at);

        return round($sorted[$lo] + ($sorted[$hi] - $sorted[$lo]) * ($at - $lo), 2);
    }
}
