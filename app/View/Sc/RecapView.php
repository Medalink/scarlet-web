<?php

namespace App\View\Sc;

use App\View\Format;

/** What the session components read out of a SessionRecap (CONTRACTS.md): the strip and route specs and the totals line. */
final class RecapView
{
    /**
     * @param  array<string, mixed>  $recap
     * @param  array<string, string>  $families  feat key => family, for the plate drawn on a feat moment
     * @return array<string, mixed>
     */
    public static function strip(array $recap, array $families = []): array
    {
        $secs = max(1, (int) $recap['buckets']['secs']);
        $mins = max(1.0, ($recap['end'] - $recap['start']) / 60);
        $combat = [];
        foreach ($recap['buckets']['combat'] as $i => $seconds) {
            if ($seconds > 0) {
                $from = $i * $secs / 60;
                $combat[] = [round($from, 2), round($from + $seconds / 60, 2)];
            }
        }
        $events = [];
        foreach ($recap['levels'] as $level) {
            $events[] = ['m' => round($level['at'] / 60, 2), 'k' => 'level', 't' => (string) $level['level'], 'tip' => 'Level '.$level['level'].' reached'];
        }
        foreach ($recap['moments'] as $moment) {
            $event = ['m' => round($moment['at'] / 60, 2), 'k' => $moment['kind'] === 'boss' || $moment['kind'] === 'discovery' ? 'q' : $moment['kind'], 'tip' => $moment['text']];
            if ($moment['kind'] === 'feat') {
                $event += ['family' => $families[$moment['feat'] ?? ''] ?? 'combat', 'tier' => $moment['tier'] ?? 1];
            }
            $events[] = $event;
        }

        return [
            't0' => $recap['start'],
            'mins' => $mins,
            'zones' => array_map(fn (array $zone): array => [$zone['name'], round($zone['secs'] / 60, 2)], $recap['zones']),
            'pace' => $recap['buckets']['xp'],
            'combat' => $combat,
            'events' => $events,
        ];
    }

    /**
     * @param  array<string, mixed>  $recap
     * @return array<string, mixed>|null
     */
    public static function route(array $recap): ?array
    {
        $sheet = $recap['route']['sheet'] ?? null;
        if ($sheet === null || count($sheet['points']) < 4) {
            return null;
        }
        $span = max(1, $recap['end'] - $recap['start']);
        $marks = [];
        foreach ($recap['levels'] as $level) {
            $marks[] = ['k' => 'level', 'f' => round($level['at'] / $span, 3), 't' => (string) $level['level']];
        }
        foreach ($recap['moments'] as $moment) {
            if ($moment['kind'] === 'death') {
                $marks[] = ['k' => 'death', 'f' => round($moment['at'] / $span, 3)];
            }
        }

        $breaks = $sheet['breaks'] ?? [];
        $count = intdiv(count($sheet['points']), 2);
        $air = isset($recap['route']['legs']) ? array_fill(0, $count, 0) : [];
        foreach ($recap['route']['legs'] ?? [] as $i => $leg) {
            $from = $breaks[$i] ?? 0;
            $to = $breaks[$i + 1] ?? $count;
            for ($p = $from; $p < $to; $p++) {
                $air[$p] = $leg['mode'] === 'flight' ? 1 : 0;
            }
        }

        return ['aspect' => $sheet['aspect'], 'points' => $sheet['points'], 'breaks' => $breaks, 'air' => $air, 'marks' => $marks];
    }

    /**
     * The totals line of a session card: a zero is left out.
     *
     * @param  array<string, mixed>  $recap
     * @return list<array{0: string, 1: string}>
     */
    public static function totals(array $recap): array
    {
        $xp = array_sum($recap['xp']);
        $counters = $recap['counters'];
        $rows = [
            [Format::number($xp), 'XP', $xp],
            [Format::number($counters['kills'] ?? 0), 'kills', $counters['kills'] ?? 0],
            [Format::number($counters['quests'] ?? 0), 'quests', $counters['quests'] ?? 0],
            [Format::number(self::miles($recap), 1), 'mi', self::miles($recap)],
            [Format::number($counters['deaths'] ?? 0), ($counters['deaths'] ?? 0) === 1 ? 'death' : 'deaths', $counters['deaths'] ?? 0],
        ];

        return array_map(fn (array $row): array => [$row[0], $row[1]], array_values(array_filter($rows, fn (array $row): bool => $row[2] > 0)));
    }

    /**
     * @param  array<string, mixed>  $recap
     */
    public static function miles(array $recap): float
    {
        $c = $recap['counters'];

        return round((($c['foot'] ?? 0) + ($c['walk'] ?? 0) + ($c['mount'] ?? 0) + ($c['swim'] ?? 0)) / 1760, 1);
    }
}
