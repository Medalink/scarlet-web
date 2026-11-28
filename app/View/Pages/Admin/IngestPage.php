<?php

namespace App\View\Pages\Admin;

use App\Actions\Admin\IngestPipeline;
use App\View\Charts;
use App\View\Dates;
use App\View\Format;

/** The view of /admin/ingest from the answer of IngestPipeline (docs/comps/admin-ingest.html). */
final class IngestPage
{
    private const int NAMED_GROUPS = 5;

    private const array WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    public static function data(array $p): array
    {
        $days = AdminCharts::days(IngestPipeline::DAYS);
        $today = now('UTC')->toDateString();
        /** @var list<array{day: string, group: string, parts: int, bytes: int}> $groups */
        $groups = $p['groups'];
        /** @var array<string, int> $sessions */
        $sessions = $p['sessions'];
        /** @var array<string, array{median: float, p90: float, parts: int}> $latency */
        $latency = $p['latency'];
        /** @var array{queues: list<array{name: string, length: int, throughput: int, runtime: float}>, failed: int, failed_recent: int, per_minute: float} $queues */
        $queues = $p['queues'];
        $decoded = AdminCharts::over($days, $sessions);
        $median = AdminCharts::over($days, array_map(fn (array $d): float => round($d['median'], 1), $latency));
        $p90 = AdminCharts::over($days, array_map(fn (array $d): float => round($d['p90'], 1), $latency));
        $seconds = fn (int|float $v): string => AdminCharts::short($v).'s';
        $waitSay = array_map(fn (int $i): array => Format::line(Dates::text($days[$i]), isset($latency[$days[$i]]) ? ': median '.$seconds($median[$i]).', nine parts in ten within '.$seconds($p90[$i]).'.' : ': no part decoded.'), array_keys($days));
        $slowest = $p90 === [] || max($p90) <= 0 ? null : (int) array_search(max($p90), $p90, true);

        return [
            'nav' => AdminCharts::nav('/admin/ingest'),
            'sub' => Dates::join('From an uploaded part to a decoded session. Days and hours are UTC and the charts end at the last full day, '.AdminCharts::lastDay().'. Counted ', Dates::moment((string) $p['at']), '.'),
            'figures' => [
                ['label' => 'Parts uploaded today', 'value' => Format::number(array_sum(array_map(fn (array $g): int => $g['day'] === $today ? $g['parts'] : 0, $groups))), 'icon' => null],
                ['label' => 'Parts waiting to decode', 'value' => Format::number((int) $p['undecoded']), 'icon' => null],
                ['label' => 'Parts the sweep gave up on', 'value' => Format::number((int) $p['failed_parts']), 'icon' => null],
                ['label' => 'Sessions decoded today', 'value' => Format::number($sessions[$today] ?? 0), 'icon' => null],
                ['label' => 'Median time to decode', 'value' => isset($latency[$today]) ? $seconds(round($latency[$today]['median'], 1)) : '–', 'icon' => null],
                ['label' => 'Jobs waiting', 'value' => Format::number(array_sum(array_column($queues['queues'], 'length'))), 'icon' => null],
                ['label' => 'Failed jobs', 'value' => Format::number($queues['failed']), 'icon' => null],
            ],
            ...self::groups($days, $groups),
            'queues' => self::queues($queues),
            'wait' => AdminCharts::lines($days, [['cls' => 'b thin', 'values' => $p90], ['cls' => 'a', 'values' => $median]], ['w' => 660, 'h' => 250], $waitSay, $seconds),
            'waitNote' => $slowest === null ? 'No part has been decoded in these days.' : 'Slowest day: '.Dates::text($days[$slowest]).', when one part in ten waited longer than '.$seconds($p90[$slowest]).'.',
            'decoded' => AdminCharts::bars($days, $decoded, ['w' => 480, 'h' => 250]),
            'decodedNote' => AdminCharts::most($days, $decoded, 'sessions'),
            'hours' => self::hours($p['hours']),
            'today' => self::today($p['installs_today']),
            'addons' => self::versions($p['addons'], ''),
            'decoders' => self::versions($p['decoders'], 'v'),
        ];
    }

    /**
     * The five groups with the most parts over the days, each its own part of the bars, and the rest as Other.
     *
     * @param  list<string>  $days
     * @param  list<array{day: string, group: string, parts: int, bytes: int}>  $groups
     * @return array{groups: array<string, mixed>, groupKey: list<array{label: string, class: string}>}
     */
    private static function groups(array $days, array $groups): array
    {
        $totals = [];
        foreach ($groups as $g) {
            $totals[$g['group']] = ($totals[$g['group']] ?? 0) + $g['parts'];
        }
        arsort($totals);
        $named = array_slice(array_keys($totals), 0, self::NAMED_GROUPS);
        $at = array_flip($days);
        $parts = array_fill(0, count($named) + 1, array_fill(0, count($days), 0));
        foreach ($groups as $g) {
            if (! isset($at[$g['day']])) {
                continue;
            }
            $slot = array_search($g['group'], $named, true);
            $parts[$slot === false ? count($named) : $slot][$at[$g['day']]] += $g['parts'];
        }
        $labels = [...$named, 'other groups'];
        $classes = [...array_map(fn (int $i): string => 's'.($i + 1), array_keys($named)), 'o'];
        $say = array_map(fn (int $i): array => Format::line(
            Dates::text($days[$i]),
            ': '.Format::count(array_sum(array_column($parts, $i)), 'part').'. '.implode(', ', array_map(fn (int $k): string => $labels[$k].' '.Format::number($parts[$k][$i]), array_keys($labels))).'.',
        ), array_keys($days));

        return [
            'groups' => AdminCharts::stack($days, array_map(array_values(...), $parts), $classes, $say),
            'groupKey' => array_map(fn (int $k): array => ['label' => $k < count($named) ? $labels[$k] : 'Other groups', 'class' => 'c-'.$classes[$k]], array_keys($labels)),
        ];
    }

    /**
     * @param  array{queues: list<array{name: string, length: int, throughput: int, runtime: float}>, failed: int, failed_recent: int, per_minute: float}  $queues
     * @return list<array<string, mixed>>
     */
    private static function queues(array $queues): array
    {
        $most = max([1, ...array_column($queues['queues'], 'length')]);

        return array_map(fn (array $q): array => [
            'label' => $q['name'], 'who' => 'mono',
            'value' => $q['length'] === 0 ? 'Empty' : Format::number($q['length']).' waiting',
            'share' => $q['length'] === 0 ? null : $q['length'] / $most * 100,
        ], $queues['queues']);
    }

    /**
     * @param  array<int, array<int, int>>  $grid
     * @return array{spec: array<string, mixed>, summary: array{0: string, 1: string}}
     */
    private static function hours(array $grid): array
    {
        $most = max([0, ...array_merge(...array_values(array_map(fn (array $row): array => array_values($row), $grid)))]);
        $levels = [];
        $say = [];
        $busiest = null;
        foreach (self::WEEKDAYS as $d => $day) {
            $row = [];
            foreach (range(0, 23) as $hour) {
                $count = $grid[$d + 1][$hour] ?? 0;
                $row[] = $count === 0 ? 0 : max(1, (int) ceil($count / max(1, $most) * 4));
                $say[] = Format::line($day.' '.Dates::hour($hour).' UTC', ': '.Format::count($count, 'part').' in the last four weeks.');
                if ($count > 0 && $count === $most && $busiest === null) {
                    $busiest = $day.' '.Dates::hour($hour).' UTC';
                }
            }
            $levels[] = $row;
        }
        $summary = Format::line('Busiest hour', $busiest === null ? ': nothing uploaded in the last four weeks.' : ': '.$busiest.'.');

        return ['spec' => Charts::hours($levels, $say, 'when-out', null, $summary), 'summary' => $summary];
    }

    /**
     * @param  list<array{install_id: string, name: string|null, account_id: int|null, bytes: int, requests: int}>  $rows
     * @return list<array<string, mixed>>
     */
    private static function today(array $rows): array
    {
        $most = max([1, ...array_column($rows, 'bytes')]);

        return array_map(fn (array $r): array => ['cells' => [
            ['text' => substr($r['install_id'], -8), 'code' => true],
            $r['account_id'] === null ? ['text' => '', 'note' => 'Not linked'] : '#'.$r['account_id'],
            Format::number($r['requests']),
            ['text' => Format::bytes($r['bytes']), 'bar' => round($r['bytes'] / $most * 5.6, 1).'rem'],
        ]], $rows);
    }

    /**
     * @param  array<string, int>  $versions
     * @return list<array<string, mixed>>
     */
    private static function versions(array $versions, string $prefix): array
    {
        $total = max(1, array_sum($versions));
        $most = max([1, ...array_values($versions)]);
        $rows = [];
        foreach ($versions as $version => $count) {
            $rows[] = [
                'label' => $version === '' ? 'Not reported' : $prefix.$version,
                'value' => Format::number($count).($rows === [] ? ' sessions' : '').' · '.Format::percent($count / $total),
                'share' => $count / $most * 100,
            ];
        }

        return $rows;
    }
}
