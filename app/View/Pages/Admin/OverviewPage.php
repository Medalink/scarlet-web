<?php

namespace App\View\Pages\Admin;

use App\Actions\Admin\AdminOverview;
use App\View\Charts;
use App\View\Format;
use App\View\Pages\Axis;
use Illuminate\Support\Carbon;

/** The view of /admin from the answer of AdminOverview (docs/comps/admin.html). */
final class OverviewPage
{
    private const array LEVEL_BANDS = ['1–9', '10–19', '20–29', '30–39', '40–49', '50–59', '60'];

    private const int MEGABYTE = 1048576;

    /**
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    public static function data(array $o): array
    {
        /** @var array<string, int> $t */
        $t = $o['totals'];
        /** @var array<string, array<string, int>> $daily */
        $daily = $o['daily'];
        /** @var array{day: int, week: int, month: int} $active */
        $active = $o['active'];
        $days = AdminCharts::days(AdminOverview::DAYS);
        $series = array_map(fn (array $values): array => AdminCharts::over($days, $values), $daily);
        $megabytes = array_map(fn (int|float $bytes): float => round($bytes / self::MEGABYTE, 1), $series['upload_bytes']);
        $last = count($days) - 1;
        $newSay = array_map(fn (int $i): array => Format::line(Carbon::parse($days[$i])->format('j M'), ': '.Format::count($series['accounts'][$i], 'account').', '.Format::count($series['installs'][$i], 'install').' and '.Format::count($series['characters'][$i], 'character').'.'), array_keys($days));

        return [
            'nav' => AdminCharts::nav('/admin'),
            'sub' => 'Days are UTC and the charts end at the last full day, '.AdminCharts::lastDay().'. Counted '.Carbon::parse((string) $o['at'])->utc()->format('j M Y \a\t H:i').' UTC.',
            'figures' => [
                ['label' => 'Accounts', 'value' => Format::number($t['accounts']), 'icon' => null],
                ['label' => 'Installs', 'value' => Format::number($t['installs']), 'icon' => null],
                ['label' => 'Characters', 'value' => Format::number($t['characters']), 'icon' => null],
                ['label' => 'Sessions', 'value' => Format::number($t['sessions']), 'icon' => null],
                ['label' => 'Parts', 'value' => Format::number($t['parts']), 'icon' => null],
                ['label' => 'Hours played', 'value' => Format::number(intdiv($t['played_seconds'], 3600)), 'icon' => null],
                ['label' => 'Database size', 'value' => Format::bytes($t['database_bytes']), 'icon' => null],
            ],
            'keys' => AdminCharts::split([['c-s1', 'With a key', $t['accounts_with_keys']], ['c-o', 'No key', max(0, $t['accounts'] - $t['accounts_with_keys'])]]),
            'links' => AdminCharts::split([
                ['c-s1', 'Linked', $t['installs_linked']],
                ['c-s5', 'Not linked', max(0, $t['installs'] - $t['installs_linked'] - $t['installs_revoked'])],
                ['c-o', 'Revoked', $t['installs_revoked']],
            ]),
            'visibility' => AdminCharts::split([['c-s1', 'Public', $t['characters_public']], ['c-o', 'Private', max(0, $t['characters'] - $t['characters_public'])]]),
            'new' => AdminCharts::lines($days, [
                ['cls' => 'c thin', 'values' => $series['characters']],
                ['cls' => 'b thin', 'values' => $series['installs']],
                ['cls' => 'a', 'values' => $series['accounts']],
            ], AdminCharts::WIDE, $newSay),
            'active' => AdminCharts::lines($days, [['cls' => 'a', 'values' => $series['active_installs']]], AdminCharts::THIRD),
            'activeSpans' => [['Today', $active['day']], ['Last 7 days', $active['week']], ['Last 30 days', $active['month']]],
            'parts' => AdminCharts::lines($days, [['cls' => 'a', 'values' => $series['parts']]], AdminCharts::THIRD),
            'partsNote' => AdminCharts::most($days, $series['parts'], 'parts'),
            'bytes' => AdminCharts::lines($days, [['cls' => 'a', 'values' => $megabytes]], AdminCharts::THIRD),
            'bytesNote' => AdminCharts::most($days, $megabytes, 'MB, compressed,'),
            'sessions' => AdminCharts::lines($days, [['cls' => 'a', 'values' => $series['sessions']]], AdminCharts::THIRD),
            'sessionsNote' => AdminCharts::most($days, $series['sessions'], 'sessions'),
            'classes' => self::classes($o['classes'], $t['characters']),
            ...self::levels($o['levels']),
            'factions' => self::factions($o['factions']),
            'rulesets' => self::rulesets($o['rulesets'], $t['characters']),
        ];
    }

    /**
     * @param  array<string, int>  $classes
     * @return list<array<string, mixed>>
     */
    private static function classes(array $classes, int $characters): array
    {
        $top = max([1, ...array_values($classes)]);
        $rows = [];
        foreach ($classes as $file => $count) {
            $rows[] = [
                'label' => $file === '' ? 'Not known yet' : Format::className($file),
                'value' => Format::number($count).($rows === [] ? ' characters' : '').' · '.Format::percent($count / max(1, $characters)),
                'share' => $count / $top * 100,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, int>  $levels
     * @return array{levels: array<string, mixed>, levelsNote: string}
     */
    private static function levels(array $levels): array
    {
        $counts = array_map(fn (int $band): int => $levels[$band] ?? 0, array_keys(self::LEVEL_BANDS));
        $axis = Axis::nice((float) max([4, ...$counts]));
        $total = array_sum($counts);
        $running = 0;
        $median = self::LEVEL_BANDS[0];
        foreach ($counts as $band => $count) {
            $running += $count;
            if ($running * 2 >= $total) {
                $median = self::LEVEL_BANDS[$band];
                break;
            }
        }

        return [
            'levels' => Charts::bars(
                ['w' => 480, 'h' => 250, 'x0' => -0.5, 'x1' => 6.5, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks']), 'xt' => array_map(fn (int $i, string $band): array => [$i, $band], array_keys(self::LEVEL_BANDS), self::LEVEL_BANDS)],
                array_map(fn (int $i, int $count): array => [$i, $count], array_keys($counts), $counts),
                't',
            ),
            'levelsNote' => $total === 0 ? 'No character has a level yet.' : 'Half of all characters are at levels '.$median.' or below.',
        ];
    }

    /**
     * @param  array<string, int>  $factions
     * @return list<array<string, mixed>>
     */
    private static function factions(array $factions): array
    {
        $classes = ['Horde' => 'c-s1', 'Alliance' => 'c-s4'];
        $parts = [];
        foreach ($factions as $name => $count) {
            $parts[] = [$classes[$name] ?? 'c-o', $name === '' ? 'Not known yet' : $name, $count];
        }

        return AdminCharts::split($parts);
    }

    /**
     * @param  array<string, int>  $rulesets
     * @return list<array<string, mixed>>
     */
    private static function rulesets(array $rulesets, int $characters): array
    {
        $top = max([1, ...array_values($rulesets)]);
        $rows = [];
        foreach ($rulesets as $label => $count) {
            $rows[] = ['cells' => [
                $label,
                ['text' => Format::number($count), 'bar' => round($count / $top * 5.6, 1).'rem'],
                Format::percent($count / max(1, $characters), 1),
            ]];
        }

        return $rows;
    }
}
