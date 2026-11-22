<?php

namespace App\View\Pages;

use App\View\Charts;
use App\View\Format;
use Illuminate\Support\Carbon;

/** The price history module of the item page: one chart and one table of recent days for each market. */
final class ItemPriceHistory
{
    /** How many of the latest days the table of a market lists. */
    private const int TABLE_DAYS = 14;

    private const int COPPER_PER_SILVER = 100;

    private const int COPPER_PER_GOLD = 10000;

    /**
     * @param  list<array{ruleset: string, realm: string|null, days: list<array{day: string, price: int, browse: bool}>}>  $markets  the answer of ItemPrices
     * @return list<array{id: string, name: string, spec: array<string, mixed>|null, rows: list<array<string, mixed>>}>
     */
    public static function markets(array $markets): array
    {
        $out = [];
        foreach ($markets as $i => $market) {
            $out[] = [
                'id' => 'price-'.$i,
                'name' => $market['realm'] === null ? $market['ruleset'] : $market['ruleset'].', '.$market['realm'],
                'spec' => self::chart($market['days']),
                'rows' => array_map(fn (array $day): array => ['cells' => [
                    Carbon::parse($day['day'])->format('j M Y'),
                    Format::money($day['price']),
                    $day['browse'] ? 'Browse row' : 'Search',
                ]], array_reverse(array_slice($market['days'], -self::TABLE_DAYS))),
            ];
        }

        return $out;
    }

    /**
     * A line over the days that have a price, spaced by calendar day so a gap in uploads shows as a gap.
     *
     * @param  list<array{day: string, price: int, browse: bool}>  $days
     * @return array<string, mixed>|null
     */
    private static function chart(array $days): ?array
    {
        if (count($days) < 2) {
            return null;
        }
        $first = Carbon::parse($days[0]['day']);
        $top = max(array_column($days, 'price'));
        [$divisor, $suffix] = match (true) {
            $top >= self::COPPER_PER_GOLD => [self::COPPER_PER_GOLD, 'g'],
            $top >= self::COPPER_PER_SILVER => [self::COPPER_PER_SILVER, 's'],
            default => [1, 'c'],
        };
        $points = $say = [];
        foreach ($days as $day) {
            $at = Carbon::parse($day['day']);
            $points[] = [(int) $first->diffInDays($at), $day['price'] / $divisor];
            $say[] = Format::line($at->format('j M Y'), ': '.Format::moneyText($day['price']).($day['browse'] ? ' (browse row)' : ''));
        }
        $last = count($points) - 1;
        $span = max(1, $points[$last][0]);
        $axis = Axis::nice(max(array_column($points, 1)));
        $ticks = array_map(fn (int $x): array => [$x, $first->copy()->addDays($x)->format('j M')], Axis::spread(0, $span, 4));

        return Charts::line(
            ['w' => 1100, 'h' => 240, 'x0' => 0, 'x1' => $span, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks'], $suffix), 'xt' => $ticks],
            [['cls' => 'g', 'pts' => $points, 'mark' => $last]],
            $say,
            $last,
        );
    }
}
