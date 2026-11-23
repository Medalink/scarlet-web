<?php

namespace App\View\Pages\Admin;

use App\Actions\Admin\ClientVersions;
use App\View\Format;
use Illuminate\Support\Carbon;

/**
 * The view data of /admin/clients from ClientVersions. A User-Agent reads `scarlet-app/1.0.4 (windows)` from the
 * desktop app and `scarlet (linux)` from the command line, whose crate version is not the release; older builds send
 * `scarlet-upload` alone. A list shows its SHOWN largest rows and sums the rest as Other.
 */
final class ClientsPage
{
    private const string AGENT = '#^(?<name>[A-Za-z0-9._-]+)(?:/(?<version>[^\s(]+))?(?:\s*\((?<os>[^)]*)\))?#';

    private const array NAMES = ['scarlet-app' => 'App', 'scarlet' => 'Command line'];

    private const array SYSTEMS = ['windows' => 'Windows', 'macos' => 'macOS', 'linux' => 'Linux'];

    private const string OLD_AGENT = 'scarlet-upload';

    private const int SHOWN = 12;

    /**
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    public static function data(array $c): array
    {
        /** @var array{installs: int, month: int, week: int, revoked: int, linked: int} $t */
        $t = $c['totals'];
        /** @var list<array{client: string, month: int, week: int}> $clients */
        $clients = $c['clients'];
        $latest = is_string($c['latest']) ? ltrim($c['latest'], 'v') : null;
        $versions = [];
        $systems = [];
        $onLatest = 0;
        $unknown = 0;
        foreach ($clients as $row) {
            $agent = self::agent($row['client']);
            $versions[$agent['build']] = ($versions[$agent['build']] ?? 0) + $row['month'];
            $systems[$agent['os']] = ($systems[$agent['os']] ?? 0) + $row['month'];
            $onLatest += $latest !== null && $agent['version'] === $latest ? $row['month'] : 0;
            $unknown += $agent['version'] === null ? $row['month'] : 0;
        }
        arsort($versions);
        arsort($systems);

        return [
            'nav' => AdminCharts::nav('/admin/clients'),
            'sub' => 'Installs seen in the last '.ClientVersions::DAYS.' days, by the app build and system they last signed a request with.'.($latest === null ? '' : ' The newest release is '.$latest.'.'),
            'figures' => [
                ['label' => 'Installs seen, '.ClientVersions::DAYS.' days', 'value' => Format::number($t['month']), 'icon' => null],
                ['label' => 'Seen this week', 'value' => Format::number($t['week']), 'icon' => null],
                ['label' => $latest === null ? 'On the newest release' : 'On '.$latest, 'value' => Format::number($onLatest), 'icon' => null],
                ['label' => 'Build not known', 'value' => Format::number($unknown), 'icon' => null],
                ['label' => 'Linked to an account', 'value' => Format::number($t['linked']), 'icon' => null],
                ['label' => 'Revoked', 'value' => Format::number($t['revoked']), 'icon' => null],
            ],
            'versions' => self::rank($versions, $latest === null ? null : self::NAMES['scarlet-app'].' '.$latest),
            'systems' => self::rank($systems, null),
            'addons' => self::addons($c['addons']),
            'recent' => self::recent($c['recent']),
        ];
    }

    /** @return array{build: string, version: string|null, os: string} */
    private static function agent(string $client): array
    {
        if ($client === '') {
            return ['build' => 'Not seen since builds were recorded', 'version' => null, 'os' => 'Not known'];
        }
        if ($client === self::OLD_AGENT) {
            return ['build' => 'Older build, no version sent', 'version' => null, 'os' => 'Not known'];
        }
        preg_match(self::AGENT, $client, $part);
        $name = self::NAMES[$part['name'] ?? ''] ?? ($part['name'] ?? $client);
        $version = isset($part['version']) && $part['version'] !== '' ? $part['version'] : null;
        $os = isset($part['os']) && $part['os'] !== '' ? (self::SYSTEMS[strtolower($part['os'])] ?? $part['os']) : 'Not known';

        return ['build' => trim($name.' '.($version ?? '')), 'version' => $version, 'os' => $os];
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array<string, mixed>>
     */
    private static function rank(array $counts, ?string $newest): array
    {
        $total = max(1, array_sum($counts));
        $rest = array_sum(array_slice($counts, self::SHOWN));
        $counts = array_slice($counts, 0, self::SHOWN, true) + ($rest > 0 ? ['Other' => $rest] : []);
        $most = max([1, ...array_values($counts)]);
        $rows = [];
        foreach ($counts as $label => $count) {
            $rows[] = [
                'label' => (string) $label.($label === $newest ? ' (newest)' : ''),
                'value' => Format::number($count).($rows === [] ? ' installs' : '').' · '.Format::percent($count / $total),
                'share' => $count / $most * 100,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{version: string, characters: int, sessions: int}>  $rows
     * @return list<array<string, mixed>>
     */
    private static function addons(array $rows): array
    {
        $most = max([1, ...array_column($rows, 'characters')]);

        return array_map(fn (int $i, array $row): array => [
            'label' => $row['version'] === '' ? 'Not reported' : $row['version'],
            'value' => Format::count($row['characters'], 'character').', '.Format::count($row['sessions'], 'session'),
            'share' => $row['characters'] / $most * 100,
        ], array_keys($rows), $rows);
    }

    /**
     * @param  list<array{id: string, name: string|null, account_id: int|null, client: string|null, characters: int, revoked: bool, created_at: string|null, last_seen_at: string|null}>  $rows
     * @return list<array<string, mixed>>
     */
    private static function recent(array $rows): array
    {
        return array_map(fn (array $row): array => ['cells' => [
            ['text' => substr($row['id'], -8), 'code' => true, ...($row['revoked'] ? ['tag' => 'Revoked'] : [])],
            $row['name'] ?? '',
            $row['account_id'] === null ? ['text' => '', 'note' => 'Not linked'] : '#'.$row['account_id'],
            $row['client'] === null ? ['text' => '', 'note' => 'Not seen yet'] : ['text' => $row['client'], 'code' => true],
            Format::number($row['characters']),
            self::when($row['last_seen_at']),
            self::when($row['created_at']),
        ]], $rows);
    }

    /** @return string|array<string, string> */
    private static function when(?string $instant): string|array
    {
        if ($instant === null) {
            return '';
        }
        $at = Carbon::parse($instant)->utc();

        return ['text' => $at->format('j M H:i'), 'instant' => $at->toIso8601ZuluString(), 'format' => 'j M H:i'];
    }
}
