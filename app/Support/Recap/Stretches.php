<?php

namespace App\Support\Recap;

use Illuminate\Support\Carbon;

/** The `zone` and the `zones` of a recap, from the session's play spans. */
final class Stretches
{
    private const int MIN_MS = 30_000;

    /**
     * @return array{zone: string, dungeon: array{name: string, runs: int}|null, zones: list<array{name: string, at: int, secs: int}>}
     */
    public static function read(Scope $scope): array
    {
        $raw = [];
        $spans = $scope->rows('play_spans')->whereNotNull('zone_name')->where('zone_name', '!=', '')->orderBy('at')->get(['at', 'ms', 'zone_name', 'instance_type']);
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
            'dungeon' => $zone !== '' && ($instance[$zone] ?? false) ? ['name' => $zone, 'runs' => self::runs($scope, $zone)] : null,
            'zones' => self::merged($raw, $scope->start * 1000),
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
