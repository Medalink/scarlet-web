<?php

namespace App\View\Pages;

use App\View\Charts;
use App\View\Format;
use Illuminate\Support\Carbon;

/** The owner's view of imported history (docs/DESIGN.md, Imported history), formatted from App\Actions\Account\ShowImportedHistory. */
final class ImportedHistoryPage
{
    /**
     * One row per character that has imported records, for the account's characters page.
     *
     * @param  list<array<string, mixed>>  $characters
     * @param  array<int, array<string, mixed>>  $history
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}|null
     */
    public static function roster(array $characters, array $history): ?array
    {
        $rows = [];
        foreach ($characters as $c) {
            $h = $history[$c['id']] ?? null;
            if ($h === null) {
                continue;
            }
            $rows[] = ['cells' => [
                ['text' => Format::characterName($c['name']), 'href' => '/c/'.rawurlencode($c['name'])],
                implode(', ', $h['sources']),
                self::range($h['first'], $h['last']),
                Format::number($h['sales']),
                Format::moneyText($h['sales_copper']),
                Format::number($h['buys']),
                Format::moneyText($h['buys_copper']),
                Format::number(count($h['gold'])),
            ]];
        }
        if ($rows === []) {
            return null;
        }

        return [
            'columns' => [['label' => 'Character'], ['label' => 'From'], ['label' => 'Covers'], ['label' => 'Sales', 'numeric' => true], ['label' => 'Received', 'numeric' => true], ['label' => 'Purchases', 'numeric' => true], ['label' => 'Paid', 'numeric' => true], ['label' => 'Gold days', 'numeric' => true]],
            'rows' => $rows,
        ];
    }

    /**
     * The sections of one character's page.
     *
     * @param  array<string, mixed>  $h
     * @return array<string, mixed>
     */
    public static function character(array $h): array
    {
        $source = implode(' and ', $h['sources']);

        return [
            'source' => $source,
            'range' => self::range($h['first'], $h['last']),
            'figures' => [
                ['label' => 'Sales', 'value' => Format::number($h['sales'])],
                ['label' => 'Received', 'value' => Format::moneyText($h['sales_copper'])],
                ['label' => 'Purchases', 'value' => Format::number($h['buys'])],
                ['label' => 'Paid', 'value' => Format::moneyText($h['buys_copper'])],
                ['label' => 'Gold days', 'value' => Format::number(count($h['gold']))],
            ],
            'gold' => self::gold($h['gold']),
            'days' => $h['days'] === [] ? null : [
                'columns' => [['label' => 'Day'], ['label' => 'Sales', 'numeric' => true], ['label' => 'Received', 'numeric' => true], ['label' => 'Purchases', 'numeric' => true], ['label' => 'Paid', 'numeric' => true]],
                'rows' => array_map(fn (array $d): array => ['cells' => [
                    self::day($d['day']),
                    Format::number($d['sales']),
                    Format::moneyText($d['sales_copper']),
                    Format::number($d['buys']),
                    Format::moneyText($d['buys_copper']),
                ]], $h['days']),
            ],
            'items' => $h['items'] === [] ? null : [
                'columns' => [['label' => 'Item'], ['label' => 'Sold', 'numeric' => true], ['label' => 'Received', 'numeric' => true], ['label' => 'Each', 'numeric' => true]],
                'rows' => array_map(fn (array $i): array => ['cells' => [
                    $i['name'],
                    Format::number($i['sold']),
                    Format::moneyText($i['copper']),
                    $i['sold'] > 0 ? Format::moneyText(intdiv($i['copper'], $i['sold'])) : '',
                ]], $h['items']),
            ],
        ];
    }

    /**
     * The balance at the end of each imported day.
     *
     * @param  list<array{at: int, balance: int}>  $gold
     * @return array<string, mixed>|null
     */
    private static function gold(array $gold): ?array
    {
        $last = count($gold) - 1;
        if ($last < 1) {
            return null;
        }
        $points = $say = $ticks = [];
        foreach ($gold as $i => $row) {
            $points[] = [$i, $row['balance'] / 10000];
            $say[] = Format::line(self::day($row['at']), ': '.Format::moneyText($row['balance']));
            if ($i === 0 || $i === $last || $i % 14 === 0) {
                $ticks[] = [$i, self::day($row['at'])];
            }
        }
        $axis = Axis::nice(max([0.0, ...array_column($points, 1)]));

        return Charts::line(['x0' => 0, 'x1' => $last, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks'], 'g'), 'xt' => $ticks], [['cls' => 'g', 'pts' => $points, 'mark' => $last]], $say, $last, 'gold-out');
    }

    private static function range(int $first, int $last): string
    {
        $from = self::day($first);
        $to = self::day($last);

        return $from === $to ? $from : $from.' to '.$to;
    }

    private static function day(int $epoch): string
    {
        return Carbon::createFromTimestampUTC($epoch)->format('j M Y');
    }
}
