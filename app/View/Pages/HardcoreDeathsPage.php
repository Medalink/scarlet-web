<?php

namespace App\View\Pages;

use App\Actions\Rollups\RulesetSummaries;
use App\View\Charts;
use App\View\Format;
use Illuminate\Support\Carbon;

/** The data of the hardcore deaths page from the answer of App\Actions\Public\HardcoreDeaths. */
final class HardcoreDeathsPage
{
    /** The deaths-by-day chart shows this many days, ending with the latest day that has a death. */
    public const int DAYS = 90;

    /**
     * @param  array{levels: array<int, int>, days: array<string, int>, unknown_level: int, total: int}  $r
     * @return array{total: int, sub: string, levels: array<string, mixed>|null, days: array<string, mixed>|null}
     */
    public static function data(array $r): array
    {
        $levelled = array_sum($r['levels']);
        $sub = $r['total'] === 0
            ? 'No hardcore deaths recorded yet.'
            : Format::count($r['total'], 'death').' recorded, each counted once however many players saw the notice.'
                .($r['unknown_level'] > 0 ? ' '.Format::count($r['unknown_level'], 'notice').' did not give a level.' : '');

        return [
            'total' => $r['total'],
            'sub' => $sub,
            'levels' => $levelled === 0 ? null : self::levels($r['levels']),
            'days' => $r['days'] === [] ? null : self::days($r['days']),
        ];
    }

    /**
     * @param  array<int, int>  $levels
     * @return array<string, mixed>
     */
    private static function levels(array $levels): array
    {
        $top = max(RulesetSummaries::TARGET_LEVEL, ...array_keys($levels));
        $data = $say = [];
        for ($level = 1; $level <= $top; $level++) {
            $count = $levels[$level] ?? 0;
            $data[] = [$level, $count];
            $say[] = Format::line('Level '.$level, ': '.Format::count($count, 'death'));
        }
        $axis = Axis::nice((float) max($data === [] ? [0] : array_column($data, 1)), 3);

        return Charts::bars(['w' => 760, 'h' => 250, 'x0' => 0, 'x1' => $top + 1, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks']), 'xt' => Charts::ticks(Axis::spread(1, $top, 6))], $data, 't', $say, null, 'levels-out');
    }

    /**
     * @param  array<string, int>  $days
     * @return array<string, mixed>
     */
    private static function days(array $days): array
    {
        $last = Carbon::parse((string) array_key_last($days), 'UTC');
        $first = max(Carbon::parse((string) array_key_first($days), 'UTC'), $last->copy()->subDays(self::DAYS - 1));
        $data = $say = [];
        for ($day = $first->copy(); $day->lte($last); $day->addDay()) {
            $count = $days[$day->toDateString()] ?? 0;
            $data[] = [count($data), $count];
            $say[] = Format::line($day->format('j M'), ': '.Format::count($count, 'death'));
        }
        $axis = Axis::nice((float) max([0, ...array_column($data, 1)]), 3);
        $ticks = array_map(fn (int $index): array => [$index, $first->copy()->addDays($index)->format('j M')], Axis::spread(0, count($data) - 1, 4));

        return Charts::bars(['w' => 760, 'h' => 250, 'x0' => -1, 'x1' => count($data), 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks']), 'xt' => $ticks], $data, 't', $say, null, 'days-out');
    }
}
