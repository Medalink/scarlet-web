<?php

namespace App\Support\Recap;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;

/** The `zone` and the `zones` of a recap, from the session's play spans. */
final class Stretches
{
    private const int MIN_MS = 30_000;

    /**
     * @return array{zone: string, dungeon: array{name: string, runs: int}|null, zones: list<array{name: string, at: int, secs: int}>}
     */
    public static function read(Scope $scope): array
    {
        $spans = $scope->rows('play_spans')->whereNotNull('zone_name')->where('zone_name', '!=', '')->orderBy('at')->get(['at', 'ms', 'zone_name', 'instance_type']);

        return self::assemble($scope->start, $spans, fn (string $zone): int => self::runs($scope, $zone));
    }

    /**
     * `read()` for several sessions of one character in two queries.
     *
     * @param  list<int>  $starts
     * @return array<int, array{zone: string, dungeon: array{name: string, runs: int}|null, zones: list<array{name: string, at: int, secs: int}>}>
     */
    public static function readMany(int $character, array $starts): array
    {
        $spans = DB::table('play_spans')->where('character_id', $character)->whereIn('session_start', $starts)
            ->whereNotNull('zone_name')->where('zone_name', '!=', '')->orderBy('at')->get(['session_start', 'at', 'ms', 'zone_name', 'instance_type'])->groupBy('session_start');
        $runs = DB::table('dungeon_runs')->where('character_id', $character)->whereIn('session_start', $starts)->where('continues_previous', false)
            ->selectRaw('session_start, name, count(*) as n')->groupBy('session_start', 'name')->get()
            ->mapWithKeys(fn (object $row): array => [$row->session_start.'|'.$row->name => (int) $row->n]);

        $out = [];
        foreach ($starts as $start) {
            $out[$start] = self::assemble($start, $spans->get($start, []), fn (string $zone): int => max(1, $runs->get($start.'|'.$zone, 0)));
        }

        return $out;
    }

    /**
     * @param  iterable<stdClass>  $spans
     * @param  Closure(string): int  $runs
     * @return array{zone: string, dungeon: array{name: string, runs: int}|null, zones: list<array{name: string, at: int, secs: int}>}
     */
    private static function assemble(int $start, iterable $spans, Closure $runs): array
    {
        $raw = [];
        foreach ($spans as $span) {
            $from = self::ms((string) $span->at);
            $to = $from + (int) $span->ms;
            $last = count($raw) - 1;
            if ($last >= 0 && $raw[$last]['name'] === $span->zone_name && $raw[$last]['to'] >= $from) {
                $raw[$last]['to'] = max($raw[$last]['to'], $to);

                continue;
            }
            $raw[] = ['name' => $span->zone_name, 'from' => $from, 'to' => $to, 'instance' => in_array($span->instance_type, ['party', 'raid'], true)];
        }

        $time = [];
        $instance = [];
        foreach ($raw as $stretch) {
            $time[$stretch['name']] = ($time[$stretch['name']] ?? 0) + $stretch['to'] - $stretch['from'];
            $instance[$stretch['name']] ??= $stretch['instance'];
        }
        $zone = '';
        $most = -1;
        foreach ($time as $name => $ms) {
            if ($ms > $most) {
                [$zone, $most] = [(string) $name, $ms];
            }
        }

        return [
            'zone' => $zone,
            'dungeon' => $zone !== '' && ($instance[$zone] ?? false) ? ['name' => $zone, 'runs' => $runs($zone)] : null,
            'zones' => self::merged($raw, $start * 1000),
        ];
    }

    /**
     * A stretch of the same zone as the one before, or under 30 seconds, joins the one before it.
     *
     * @param  list<array{name: string, from: int, to: int, instance: bool}>  $raw
     * @return list<array{name: string, at: int, secs: int}>
     */
    private static function merged(array $raw, int $startMs): array
    {
        /** @var list<array{name: string, from: int, to: int}> $out */
        $out = [];
        foreach ($raw as $stretch) {
            $last = count($out) - 1;
            if ($last >= 0 && ($out[$last]['name'] === $stretch['name'] || $stretch['to'] - $stretch['from'] < self::MIN_MS)) {
                $out[$last] = ['name' => $out[$last]['name'], 'from' => $out[$last]['from'], 'to' => $stretch['to']];

                continue;
            }
            $out[] = ['name' => $stretch['name'], 'from' => $stretch['from'], 'to' => $stretch['to']];
        }

        return array_map(fn (array $stretch): array => ['name' => $stretch['name'], 'at' => intdiv(max(0, $stretch['from'] - $startMs), 1000), 'secs' => intdiv($stretch['to'] - $stretch['from'], 1000)], $out);
    }

    private static function runs(Scope $scope, string $zone): int
    {
        return max(1, $scope->rows('dungeon_runs')->where('name', $zone)->where('continues_previous', false)->count());
    }

    private static function ms(string $at): int
    {
        return (int) round(Carbon::parse($at)->getPreciseTimestamp(3));
    }
}
