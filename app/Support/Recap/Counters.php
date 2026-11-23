<?php

namespace App\Support\Recap;

use App\Support\MovementMode;
use App\Support\StateKind;
use App\Support\XpSource;
use Illuminate\Support\Facades\DB;
use stdClass;

/** The `counters` and the `xp` of a recap, from the session row and the fact tables (CONTRACTS.md, "Session recap"). A zero, and a counter no table records, is left out. */
final class Counters
{
    private const array MOVEMENT = [
        MovementMode::Run->value => ['foot', 'footTime'],
        MovementMode::Walk->value => ['walk', 'walkTime'],
        MovementMode::Mount->value => ['mount', 'mountTime'],
        MovementMode::Swim->value => ['swim', 'swimTime'],
        MovementMode::Fly->value => ['fly', 'flyTime'],
    ];

    private const array METERS = ['d' => 'damage', 'h' => 'healing', 't' => 'damageTaken', 'a' => 'absorbs', 'i' => 'interrupts', 'x' => 'dispels'];

    /** @return array<string, int> */
    public static function read(Scope $scope, stdClass $session): array
    {
        $counters = [
            'kills' => (int) $session->kills,
            'deaths' => (int) $session->deaths,
            'quests' => (int) $session->quests_done,
            'xp' => (int) $session->xp_total,
            'earned' => (int) $session->copper_earned,
            'spent' => (int) $session->copper_spent,
            'dungeons' => (int) $session->dungeon_runs,
            'levels' => $scope->rows('level_ups')->where('ding', true)->count(),
            'bosses' => $scope->rows('boss_kills')->count(),
            'emotes' => $scope->rows('emotes')->count(),
            'abandoned' => $scope->rows('quest_events')->where('event', 'abandoned')->count(),
            'discoveries' => $scope->rows('xp_gains')->where('source', XpSource::Exploring->value)->count(),
            'crafted' => (int) $scope->rows('crafts')->sum('quantity'),
            'loot' => (int) $scope->rows('loot_drops')->sum('quantity'),
            'afkTime' => intdiv((int) $scope->rows('play_spans')->sum('afk_ms'), 1000),
            'groupTime' => (int) $scope->rows('group_spans')->where('size', '>', 1)->sum(DB::raw('extract(epoch from (ended_at - at))')),
        ];

        return array_filter([...$counters, ...self::movement($scope), ...self::combat($scope), ...self::camps($scope)], fn (int $n): bool => $n !== 0);
    }

    /** @return array<string, int> */
    private static function movement(Scope $scope): array
    {
        $out = [];
        $jumps = 0;
        $rows = $scope->rows('movement_stretches')->selectRaw('mode, sum(yards) as yards, sum(seconds) as seconds, sum(jumps) as jumps')->groupBy('mode')->get();
        foreach ($rows as $row) {
            $jumps += (int) $row->jumps;
            if (isset(self::MOVEMENT[$row->mode])) {
                [$yards, $time] = self::MOVEMENT[$row->mode];
                $out[$yards] = (int) $row->yards;
                $out[$time] = (int) $row->seconds;
            }
        }
        $flights = $scope->rows('flights')->selectRaw('count(*) as n, coalesce(sum(ms), 0) as ms')->first();

        return $out + ['jumps' => $jumps, 'flights' => (int) $flights?->n, 'taxiTime' => intdiv((int) $flights?->ms, 1000)];
    }

    /** @return array<string, int> */
    private static function combat(Scope $scope): array
    {
        $out = [];
        foreach ($scope->rows('meter_totals')->selectRaw('meter, sum(total) as total')->groupBy('meter')->get() as $row) {
            if (isset(self::METERS[$row->meter])) {
                $out[self::METERS[$row->meter]] = (int) $row->total;
            }
        }
        $casts = $scope->rows('spell_casts')->selectRaw('coalesce(sum(casts), 0) as n, coalesce(sum(cast_ms), 0) as ms')->first();

        return $out + [
            'casts' => (int) $casts?->n,
            'castTime' => intdiv((int) $casts?->ms, 1000),
            'combat' => intdiv((int) $scope->rows('state_times')->where('kind', StateKind::Fight->value)->sum('ms'), 1000),
        ];
    }

    /** @return array<string, int> */
    private static function camps(Scope $scope): array
    {
        $camps = $scope->rows('camp_stays')->selectRaw('count(*) as n, coalesce(sum(seconds), 0) as seconds')->first();

        return ['camps' => (int) $camps?->n, 'camptime' => (int) $camps?->seconds];
    }

    /** @return array{kills: int, dungeons: int, quests: int, explore: int, other: int} */
    public static function xp(Scope $scope): array
    {
        $by = $scope->rows('xp_gains')->selectRaw('source, sum(amount) as amount')->groupBy('source')->pluck('amount', 'source');

        return [
            'kills' => (int) ($by['k'] ?? 0),
            'dungeons' => (int) ($by['d'] ?? 0),
            'quests' => (int) ($by['q'] ?? 0),
            'explore' => (int) ($by['e'] ?? 0),
            'other' => (int) ($by['o'] ?? 0),
        ];
    }

    /** @return array<string, int> */
    public static function gathered(Scope $scope): array
    {
        return $scope->rows('gathers')->where('looted', true)->selectRaw('kind, count(*) as n')->groupBy('kind')->pluck('n', 'kind')->map(fn (mixed $n): int => (int) $n)->all();
    }

    /**
     * `gathered()` for several sessions of one character in one query.
     *
     * @param  list<int>  $starts
     * @return array<int, array<string, int>>
     */
    public static function gatheredMany(int $character, array $starts): array
    {
        $out = [];
        foreach (DB::table('gathers')->where('character_id', $character)->whereIn('session_start', $starts)->where('looted', true)
            ->selectRaw('session_start, kind, count(*) as n')->groupBy('session_start', 'kind')->get() as $row) {
            $out[(int) $row->session_start][(string) $row->kind] = (int) $row->n;
        }

        return $out;
    }
}
