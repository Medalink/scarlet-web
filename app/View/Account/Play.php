<?php

namespace App\View\Account;

use App\View\Character\PlayTime;
use App\View\Dates;
use App\View\Format;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/** The play time stack, the gold line and the weekday grid of /account. */
final class Play
{
    /** Characters named in the stack; the others share one part. */
    private const int NAMED = 4;

    private const int SPAN = 30;

    /**
     * @param  list<array<string, mixed>>  $characters  the roster, most played first
     * @param  array<string, array<int, float>>  $days  day (Y-m-d) → character id → seconds
     * @return array{spec: array<string, mixed>, key: list<array{0: string, 1: string, 2: string}>, sentence: string}|null
     */
    public static function days(array $characters, array $days): ?array
    {
        if ($days === []) {
            return null;
        }
        $series = [];
        foreach ($characters as $i => $c) {
            $series[min($i, self::NAMED)][] = $c['id'];
        }
        $names = [];
        foreach (array_keys($series) as $position => $slot) {
            $names[] = [$slot === self::NAMED ? 'Other' : Format::characterName($characters[$slot]['name']), '--s-'.($position + 1)];
        }
        $first = Carbon::parse((string) array_key_first($days), 'UTC');
        $count = (int) $first->diffInDays(Carbon::parse((string) array_key_last($days), 'UTC')) + 1;
        $rows = [];
        $sums = array_fill(0, count($series), 0.0);
        $longest = [$first, 0.0];
        $dayCount = 0;
        for ($i = 0; $i < $count; $i++) {
            $day = $first->copy()->addDays($i);
            $seconds = $days[$day->toDateString()] ?? [];
            $stack = [];
            foreach (array_values($series) as $slot => $ids) {
                $sum = array_sum(array_map(fn (int $id): float => $seconds[$id] ?? 0.0, $ids));
                $sums[$slot] += $sum;
                $stack[] = round($sum / 3600, 3);
            }
            $total = array_sum($seconds);
            $dayCount += $total > 0 ? 1 : 0;
            $longest = $total > $longest[1] ? [$day, $total] : $longest;
            $rows[] = [Dates::text($day->toDateString()), $stack];
        }
        $top = max([0.0, ...array_map('array_sum', array_column($rows, 1))]);

        return [
            'spec' => ['unit' => 'h', 'ymax' => max(1, (int) ceil($top)), 'series' => $names, 'rows' => $rows],
            'key' => array_map(fn (array $name, float $sum): array => [$name[0], Format::hours($sum), 'var('.$name[1].')'], $names, $sums),
            'sentence' => Format::hours(array_sum($sums)).' on '.$dayCount.' of '.$count.' days. The longest day was <b>'.Dates::weekday($longest[0]->toDateString()).'</b>: '.Format::hours($longest[1]).'.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $characters
     * @param  array{totals: array<int, array<string, int>>, balances: list<array{character_id: int, at: string, balance: int}>}  $gold
     * @param  array<string, array<int, float>>  $days
     * @return array{spec: array<string, mixed>, sentence: string}|null
     */
    public static function gold(array $characters, array $gold, array $days): ?array
    {
        if ($gold['balances'] === []) {
            return null;
        }
        $last = Carbon::parse($gold['balances'][array_key_last($gold['balances'])]['at'], 'UTC')->startOfDay();
        $first = $last->copy()->subDays(self::SPAN - 1);
        $held = $points = [];
        $next = 0;
        $balances = $gold['balances'];
        for ($i = 0; $i < self::SPAN; $i++) {
            $end = $first->copy()->addDays($i)->endOfDay();
            while (isset($balances[$next]) && Carbon::parse($balances[$next]['at'], 'UTC')->lte($end)) {
                $held[$balances[$next]['character_id']] = $balances[$next]['balance'];
                $next++;
            }
            $points[] = (int) floor(array_sum($held) / 10000);
        }
        $start = $points[0];
        $now = $points[self::SPAN - 1];
        $diff = $now - $start;

        return [
            'spec' => ['name' => 'All characters', 'unit' => 'g', 'ymax' => max(1, (int) ceil(max($points) * 1.1)), 'xlabels' => [[0, Dates::text($first->toDateString())], [14, Dates::text($first->copy()->addDays(14)->toDateString())], [self::SPAN - 1, Dates::text($last->toDateString())]], 'points' => $points],
            'sentence' => Format::number($now).'g across '.Format::count(count($held), 'character').($diff === 0 ? ', the same as '.self::SPAN.' days ago.' : ', <b>'.Format::number(abs($diff)).'g '.($diff > 0 ? 'more' : 'less').'</b> than '.self::SPAN.' days ago.'),
        ];
    }

    /**
     * @param  list<array{0: int, 1: int}>  $buckets
     * @return array{cells: list<array{level: int, tip: string}>, say: HtmlString|null}|null
     */
    public static function heat(array $buckets): ?array
    {
        if ($buckets === []) {
            return null;
        }
        $grid = PlayTime::grid($buckets);
        $busiest = $grid['busiest'];

        return ['cells' => $grid['cells'], 'say' => $busiest === null ? null : Dates::join('Your busiest hour is ', Dates::weekHour($busiest), '.')];
    }
}
