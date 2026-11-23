<?php

namespace App\Support\Recap;

use App\View\Format;
use Illuminate\Support\Facades\DB;

/** The `compare` of a recap: this session's XP, kills and quests an hour against the sessions of its ruleset that began in the same ten levels. */
final class Peers
{
    /** Sessions a band needs before it is drawn. */
    public const int MIN_SESSIONS = 20;

    private const int SENTENCE_FROM = 50;

    private const array CUTS = ['p10' => 1, 'p25' => 4, 'p50' => 9, 'p75' => 14, 'p90' => 17];

    private const array WORDS = ['xp_h' => ' XP an hour', 'kills_h' => ' kills an hour', 'quests_h' => ' quests an hour'];

    /**
     * @param  array<string, int|float>  $you  xp_h, kills_h and quests_h of the session
     * @return array{bracket: array{0: int, 1: int}, sessions: int, metrics: array<string, array<string, int|float>>, sentence: string|null}|null null when no metric has a band
     */
    public static function read(int $rulesetId, int $levelStart, array $you): ?array
    {
        $bracket = intdiv($levelStart, 10) * 10;
        $rows = DB::table('session_rates_bracket')->where('ruleset_id', $rulesetId)->where('bracket', $bracket)->where('n', '>=', self::MIN_SESSIONS)->get(['metric', 'n', 'cuts']);
        $metrics = [];
        $sessions = 0;
        foreach ($rows as $row) {
            if (! isset(self::WORDS[$row->metric])) {
                continue;
            }
            /** @var list<float> $cuts */
            $cuts = json_decode((string) $row->cuts, true, flags: JSON_THROW_ON_ERROR);
            $value = (float) ($you[$row->metric] ?? 0);
            $metrics[$row->metric] = [...array_map(fn (int $i): float => round($cuts[$i], 2), self::CUTS), 'you' => round($value, 2), 'percent' => self::percent($cuts, $value)];
            $sessions = max($sessions, (int) $row->n);
        }
        if ($metrics === []) {
            return null;
        }
        $range = [$bracket, $bracket + 9];

        return ['bracket' => $range, 'sessions' => $sessions, 'metrics' => $metrics, 'sentence' => self::sentence($metrics, $range)];
    }

    /** @param  list<float>  $cuts  the 5th to 95th percentile in steps of five */
    private static function percent(array $cuts, float $value): int
    {
        if ($value < $cuts[0]) {
            return 0;
        }
        $last = count($cuts) - 1;
        for ($i = $last - 1; $i >= 0; $i--) {
            if ($value >= $cuts[$i]) {
                $width = $cuts[$i + 1] - $cuts[$i];

                return (int) floor(5 * ($i + 1) + ($width > 0 ? 5 * min(1.0, ($value - $cuts[$i]) / $width) : 0));
            }
        }

        return 95;
    }

    /**
     * @param  array<string, array<string, int|float>>  $metrics
     * @param  array{0: int, 1: int}  $range
     */
    private static function sentence(array $metrics, array $range): ?string
    {
        $best = null;
        foreach ($metrics as $metric => $spread) {
            if ($spread['you'] > 0 && $spread['percent'] >= self::SENTENCE_FROM && ($best === null || $spread['percent'] > $metrics[$best]['percent'])) {
                $best = $metric;
            }
        }
        if ($best === null) {
            return null;
        }
        $you = $metrics[$best]['you'];

        return Format::number($you, $you < 10 ? 1 : 0).self::WORDS[$best].': more than '.$metrics[$best]['percent'].'% of sessions played at levels '.$range[0].' to '.$range[1];
    }
}
