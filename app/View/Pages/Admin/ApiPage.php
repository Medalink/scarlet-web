<?php

namespace App\View\Pages\Admin;

use App\Support\ClientAddress;
use App\View\Dates;
use App\View\Format;
use Illuminate\Support\HtmlString;

/** The view of /admin/api from the answer of ApiUsage (docs/comps/admin-api.html). */
final class ApiPage
{
    private const int NAMED_CHARACTERS = 2;

    /**
     * @param  array<string, mixed>  $a
     * @return array<string, mixed>
     */
    public static function data(array $a): array
    {
        $keep = (int) $a['days'];
        /** @var array<string, int> $t */
        $t = $a['totals'];
        /** @var array<string, array<string, int>> $daily */
        $daily = $a['daily'];
        $days = AdminCharts::days($keep);
        $column = fn (string $key): array => AdminCharts::over($days, array_map(fn (array $day): int => $day[$key], $daily));
        [$keyless, $keyed, $upload] = [$column('keyless'), $column('keyed'), $column('upload')];
        [$addresses, $accounts, $installs] = [$column('addresses'), $column('accounts'), $column('installs')];
        $sum = fn (string $key): int => array_sum(array_column($daily, $key));
        $say = array_map(fn (int $i): array => Format::line(Dates::text($days[$i]), ': '.Format::count($keyless[$i] + $keyed[$i], 'request').', '.Format::number($keyless[$i]).' without a valid key and '.Format::number($keyed[$i]).' with a key.'), array_keys($days));

        return [
            'nav' => AdminCharts::nav('/admin/api'),
            'days' => $keep,
            'sub' => 'Requests in the last '.$keep.' days. Addresses are kept for '.$keep.' days.',
            'figures' => [
                ['label' => 'Requests without a valid key', 'value' => Format::number($sum('keyless')), 'icon' => null],
                ['label' => 'Requests with a key', 'value' => Format::number($sum('keyed')), 'icon' => null],
                ['label' => 'Upload requests', 'value' => Format::number($sum('upload')), 'icon' => null],
                ['label' => 'Addresses', 'value' => Format::number($t['addresses']), 'icon' => null],
                ['label' => 'Accounts using a key', 'value' => Format::number($t['accounts']), 'icon' => null],
                ['label' => 'Installs uploading', 'value' => Format::number($t['installs']), 'icon' => null],
            ],
            'public' => AdminCharts::stack($days, [$keyless, $keyed], ['s1', 's5'], $say),
            'upload' => AdminCharts::bars($days, $upload, AdminCharts::THIRD, 't'),
            'addresses' => AdminCharts::lines($days, [['cls' => 'a', 'values' => $addresses]], AdminCharts::THIRD),
            'addressesNote' => AdminCharts::most($days, $addresses, 'addresses'),
            'accounts' => AdminCharts::lines($days, [['cls' => 'a', 'values' => $accounts]], AdminCharts::THIRD),
            'accountsNote' => AdminCharts::most($days, $accounts, 'accounts'),
            'installs' => AdminCharts::lines($days, [['cls' => 'a', 'values' => $installs]], AdminCharts::THIRD),
            'installsNote' => AdminCharts::most($days, $installs, 'installs'),
            'addressRows' => self::addresses($a['addresses']),
            'accountRows' => self::accounts($a['accounts'], (int) $a['perMinute']),
            'endpoints' => self::endpoints($a['endpoints']),
            'installRows' => self::installs($a['installs']),
            'characters' => self::characters($a['characters']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function addresses(array $rows): array
    {
        $top = max([1, ...array_column($rows, 'requests')]);

        return array_map(fn (int $i, array $row): array => ['cells' => [
            (string) ($i + 1),
            ['text' => ClientAddress::shown((string) $row['address']), 'code' => true],
            ['text' => Format::number((int) $row['requests']), 'bar' => round((int) $row['requests'] / $top * 9, 1).'rem'],
            Format::number((int) $row['endpoints']),
            self::when((string) $row['first_at']),
            self::when((string) $row['last_at']),
            $row['accounts'] === '' ? ['text' => '', 'note' => 'No key sent'] : '#'.str_replace(',', ', #', (string) $row['accounts']),
        ]], array_keys($rows), $rows);
    }

    /**
     * @param  list<array{account_id: int, keys: int, expired: int, newest: string|null, soonest: string|null, last_at: string|null, today: int, week: int, requests: int, busiest: int, limited: int}>  $rows
     * @return list<array<string, mixed>>
     */
    private static function accounts(array $rows, int $perMinute): array
    {
        $top = max([1, ...array_column($rows, 'week')]);

        return array_map(fn (array $row): array => ['cells' => [
            '#'.$row['account_id'],
            $row['keys'] === 0 ? ['text' => '0', 'note' => 'deleted'] : ['text' => Format::number($row['keys']), ...($row['expired'] > 0 ? ['note' => Format::number($row['expired']).' expired'] : [])],
            $row['newest'] === null ? '' : ['date' => Dates::day($row['newest'])],
            $row['soonest'] !== null ? ['date' => Dates::day($row['soonest'])] : ['text' => '', 'note' => $row['keys'] === 0 ? '' : 'All expired'],
            $row['last_at'] === null ? ['text' => '', 'note' => 'Never'] : self::when($row['last_at']),
            Format::number($row['today']),
            ['text' => Format::number($row['week']), 'bar' => round($row['week'] / $top * 6, 1).'rem'],
            Format::number($row['requests']),
            ['text' => Format::number($row['busiest']).' of '.Format::number($perMinute), ...($row['busiest'] >= $perMinute ? ['tag' => 'At the limit'] : [])],
            Format::number($row['limited']),
        ]], $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function endpoints(array $rows): array
    {
        $top = max([1, ...array_column($rows, 'requests')]);

        return array_map(fn (int $i, array $row): array => [
            'label' => preg_replace('#^(\S+) api/v1#', '$1 ', (string) $row['endpoint']) ?? (string) $row['endpoint'],
            'value' => Format::number((int) $row['requests']).($i === 0 ? ' requests' : ''),
            'share' => (int) $row['requests'] / $top * 100,
            'who' => 'mono',
        ], array_keys($rows), $rows);
    }

    /**
     * @param  list<array{install_id: string, name: string|null, account_id: int|null, characters: list<string>, requests: int, bytes: int, last_at: string}>  $rows
     * @return list<array<string, mixed>>
     */
    private static function installs(array $rows): array
    {
        return array_map(function (array $row): array {
            $names = array_slice($row['characters'], 0, self::NAMED_CHARACTERS);
            $more = count($row['characters']) - count($names);

            return ['cells' => [
                ['text' => substr($row['install_id'], -8), 'code' => true],
                $row['account_id'] === null ? ['text' => '', 'note' => 'Not linked'] : '#'.$row['account_id'],
                $names === [] ? ['text' => '', 'note' => 'None yet'] : implode(', ', $names).($more > 0 ? ' and '.$more.' more' : ''),
                Format::bytes($row['bytes']),
                Format::number($row['requests']),
            ]];
        }, $rows);
    }

    /**
     * @param  list<array{name: string, known: bool, requests: int}>  $rows
     * @return list<array<string, mixed>>
     */
    private static function characters(array $rows): array
    {
        $top = max([1, ...array_column($rows, 'requests')]);

        return array_map(fn (int $i, array $row): array => [
            'label' => $row['name'],
            'href' => $row['known'] ? '/c/'.rawurlencode($row['name']) : null,
            'value' => Format::number($row['requests']).($i === 0 ? ' requests' : ''),
            'share' => $row['requests'] / $top * 100,
        ], array_keys($rows), $rows);
    }

    /** @return array{date: HtmlString} */
    private static function when(string $instant): array
    {
        return ['date' => Dates::moment($instant)];
    }
}
