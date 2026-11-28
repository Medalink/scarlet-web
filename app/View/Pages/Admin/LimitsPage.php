<?php

namespace App\View\Pages\Admin;

use App\Support\ClientAddress;
use App\View\Dates;
use App\View\Format;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/** The view data of /admin/limits from ApiLimits. */
final class LimitsPage
{
    private const int NEAR_CAP = 80;

    /** The first UTC day `api_calls.limited` counted; before it a 429 counts only as refused. */
    private const string LIMITED_SINCE = '2026-11-23';

    /**
     * @param  array<string, mixed>  $l
     * @return array<string, mixed>
     */
    public static function data(array $l): array
    {
        $keep = (int) $l['days'];
        /** @var array<string, int> $b */
        $b = $l['budgets'];
        /** @var array{requests: int, refused: int, limited: int, addresses: int, limited_addresses: int} $t */
        $t = $l['today'];
        /** @var array<string, array{requests: int, refused: int, limited: int}> $daily */
        $daily = $l['daily'];
        $days = AdminCharts::days($keep);
        $column = fn (string $key): array => AdminCharts::over($days, array_map(fn (array $day): int => $day[$key], $daily));
        [$requests, $refused, $limited] = [$column('requests'), $column('refused'), $column('limited')];
        $other = array_map(fn (int|float $all, int|float $over): int => (int) max(0, $all - $over), $refused, $limited);
        $say = array_map(fn (int $i): array => Format::line(Dates::text($days[$i]), ': '.Format::count($limited[$i], 'request').' answered 429, '.Format::number($other[$i]).' refused otherwise.'), array_keys($days));

        return [
            'nav' => AdminCharts::nav('/admin/limits'),
            'sub' => 'Who reaches the limits and how close uploads come to their caps. Today is the UTC day so far; counts are kept '.$keep.' days.',
            'figures' => [
                ['label' => 'Requests today', 'value' => Format::number($t['requests']), 'icon' => null],
                ['label' => 'Refused today', 'value' => Format::number($t['refused']), 'icon' => null],
                ['label' => 'Answered 429 today', 'value' => Format::number($t['limited']), 'icon' => null],
                ['label' => 'Addresses today', 'value' => Format::number($t['addresses']), 'icon' => null],
                ['label' => 'Addresses limited today', 'value' => Format::number($t['limited_addresses']), 'icon' => null],
            ],
            'refusals' => AdminCharts::stack($days, [$limited, $other], ['s1', 's5'], $say),
            'refusalsNote' => '429 answers are counted apart from '.Dates::text(self::LIMITED_SINCE).'; before that day they show as refused otherwise.',
            'requests' => AdminCharts::bars($days, $requests, AdminCharts::THIRD, 't'),
            'requestsNote' => AdminCharts::most($days, $requests, 'requests'),
            'budgets' => [
                ['Public API without a key', Format::number($b['public_anonymous']).' a minute per address'],
                ['Public API with a key', Format::number($b['public_token']).' a minute per account'],
                ['Bad API keys', Format::number($b['bad_tokens']).' a minute per address, then 429'],
                ['Uploads per install', Format::number($b['upload_install']).' per '.$b['upload_seconds'].'s'],
                ['Uploads per address', Format::number($b['upload_ip']).' per '.$b['upload_seconds'].'s'],
                ['New installs per address', Format::number($b['registration']).' per '.Format::duration($b['registration_seconds'])],
                ['Pages per address', Format::number($b['page']).' per '.$b['page_seconds'].'s'],
                ['Upload bytes per install', Format::bytes($b['bytes_per_day']).' a day'],
                ['Characters per install', Format::number($b['characters_per_install'])],
            ],
            'limited' => self::limited($l['limited']),
            'busiest' => self::busiest($l['busiest'], $b['public_anonymous']),
            'uploads' => self::uploads($l['uploads'], $b['bytes_per_day']),
            'characters' => self::characters($l['characters'], $b['characters_per_install']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function limited(array $rows): array
    {
        $top = max([1, ...array_column($rows, 'limited')]);

        return array_map(fn (array $row): array => ['cells' => [
            ['text' => ClientAddress::shown((string) $row['address']), 'code' => true],
            ['text' => Format::number((int) $row['limited']), 'bar' => round((int) $row['limited'] / $top * 6, 1).'rem'],
            Format::number((int) $row['refused']),
            Format::number((int) $row['requests']),
            Format::number((int) $row['endpoints']),
            self::accounts((string) $row['accounts']),
            self::when((string) $row['last_at']),
        ]], $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function busiest(array $rows, int $anonymousPerMinute): array
    {
        return array_map(function (array $row) use ($anonymousPerMinute): array {
            $minutes = max(1, (int) ceil(Carbon::parse((string) $row['first_at'])->diffInSeconds(Carbon::parse((string) $row['last_at'])) / 60));
            $rate = (int) $row['requests'] / $minutes;

            return ['class' => $rate >= $anonymousPerMinute ? 'me' : null, 'cells' => [
                ['text' => ClientAddress::shown((string) $row['address']), 'code' => true],
                Format::number((int) $row['requests']),
                Format::number($rate, 1),
                Format::number((int) $row['limited']),
                Format::number((int) $row['refused']),
                self::accounts((string) $row['accounts']),
                self::when((string) $row['first_at']),
                self::when((string) $row['last_at']),
            ]];
        }, $rows);
    }

    /**
     * @param  list<array{install_id: string, name: string|null, account_id: int|null, bytes: int, requests: int}>  $rows
     * @return list<array<string, mixed>>
     */
    private static function uploads(array $rows, int $cap): array
    {
        return array_map(function (array $row) use ($cap): array {
            $share = $row['bytes'] / max(1, $cap) * 100;

            return ['cells' => [
                ['text' => substr($row['install_id'], -8), 'code' => true, ...($share >= self::NEAR_CAP ? ['tag' => 'Near the cap'] : [])],
                $row['name'] ?? '',
                $row['account_id'] === null ? ['text' => '', 'note' => 'Not linked'] : '#'.$row['account_id'],
                Format::bytes($row['bytes']),
                ['text' => Format::number(min(100, $share), 1).'%', 'bar' => round(min(100, $share) / 100 * 6, 1).'rem'],
                Format::number($row['requests']),
            ]];
        }, $rows);
    }

    /**
     * @param  list<array{install_id: string, name: string|null, account_id: int|null, characters: int}>  $rows
     * @return list<array<string, mixed>>
     */
    private static function characters(array $rows, int $cap): array
    {
        return array_map(fn (array $row): array => ['cells' => [
            ['text' => substr($row['install_id'], -8), 'code' => true, ...($cap * self::NEAR_CAP <= $row['characters'] * 100 ? ['tag' => 'Near the cap'] : [])],
            $row['name'] ?? '',
            $row['account_id'] === null ? ['text' => '', 'note' => 'Not linked'] : '#'.$row['account_id'],
            ['text' => Format::number($row['characters']).' of '.Format::number($cap), 'bar' => round(min(1, $row['characters'] / max(1, $cap)) * 6, 1).'rem'],
        ]], $rows);
    }

    /** @return string|array<string, string> */
    private static function accounts(string $ids): string|array
    {
        return $ids === '' ? ['text' => '', 'note' => 'No key sent'] : '#'.str_replace(',', ', #', $ids);
    }

    /** @return array{date: HtmlString} */
    private static function when(string $instant): array
    {
        return ['date' => Dates::moment($instant)];
    }
}
