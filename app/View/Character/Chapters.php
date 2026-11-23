<?php

namespace App\View\Character;

use App\Models\Character;
use App\Support\ClassPage\Unit;
use App\View\Format;
use App\View\Pages\BattlegroundsPage;
use Illuminate\Support\HtmlString;

/** The levelling, combat, loot and professions sections of /c/{name}. */
final class Chapters
{
    private const array SOURCES = ['k' => ['Kills', '--s-kill'], 'e' => ['Exploring', '--s-explore'], 'q' => ['Quests', '--s-quest'], 'd' => ['Dungeons', '--s-dungeon'], 'o' => ['Other', '--s-other']];

    private const array QUALITIES = [0 => ['Poor', '--s-0'], 1 => ['Common', '--s-0'], 2 => ['Uncommon', '--s-2'], 3 => ['Rare', '--s-4'], 4 => ['Epic', '--s-1'], 5 => ['Legendary', '--s-3']];

    private const int TOP = 5;

    private const int LAST_LEVELS = 3;

    private const int MEDIAN_FROM = 2;

    private const string PARTIAL = 'rgba(255,45,85,0.45)';

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>|null
     */
    public static function levelling(array $r, string $first): ?array
    {
        $own = $r['levelTimes']['character'];
        if (count($own) < 2) {
            return null;
        }
        $medians = [];
        foreach ($r['levelTimes']['ruleset'] as $row) {
            if ((int) $row['completed'] >= self::MEDIAN_FROM) {
                $medians[(int) $row['level']] = (float) $row['median'] / 3600;
            }
        }
        $current = (int) $r['character']->level;
        $rows = [];
        foreach ($own as $row) {
            $median = isset($medians[$row['level']]) ? round($medians[$row['level']], 1) : null;
            $rows[] = [(string) $row['level'], round($row['seconds'] / 3600, 1), $median, $row['level'] === $current ? self::PARTIAL : null];
        }
        $ymax = max(1, (int) ceil(max([0.0, ...array_map(fn (array $row): float => max($row[1], $row[2] ?? 0), $rows)])));

        return [
            'cols' => ['rows' => $rows, 'unit' => 'h', 'xname' => 'Level', 'name' => $first, 'fmt' => '#h', 'every' => 5, 'ymax' => $ymax],
            'ruleset' => $r['ruleset']['name'] ?? null,
            'sentence' => self::slowest($own, $medians, $current),
            'xp' => self::xpStack($r['xp']),
            'last' => self::lastLevels($own, $current),
        ];
    }

    /**
     * @param  list<array{level: int, seconds: int}>  $own
     * @param  array<int, float>  $medians
     */
    private static function slowest(array $own, array $medians, int $current): ?HtmlString
    {
        $worst = null;
        foreach ($own as $row) {
            $gap = isset($medians[$row['level']]) ? $row['seconds'] / 3600 - $medians[$row['level']] : 0;
            if ($row['level'] !== $current && $gap > ($worst['gap'] ?? 0)) {
                $worst = ['level' => $row['level'], 'hours' => $row['seconds'] / 3600, 'gap' => $gap];
            }
        }
        if ($worst === null) {
            return null;
        }
        $later = array_filter($own, fn (array $row): bool => $row['level'] > $worst['level'] && $row['level'] !== $current && isset($medians[$row['level']]));
        $slower = array_filter($later, fn (array $row): bool => $medians[$row['level']] < $row['seconds'] / 3600);
        $text = e('Level '.$worst['level'].' took '.Format::hours($worst['hours'] * 3600).'; the median is '.Format::hours($medians[$worst['level']] * 3600).'.');

        return new HtmlString($text.($later !== [] && $slower === [] ? ' Every level since has been <b>faster than the median</b>.' : ''));
    }

    /**
     * @param  list<array{level: int, source: string, xp: string|int}>  $xp
     * @return list<array{0: string, 1: int, 2: string, 3: string}>
     */
    private static function xpStack(array $xp): array
    {
        $sum = [];
        foreach ($xp as $row) {
            $sum[$row['source']] = ($sum[$row['source']] ?? 0) + (int) $row['xp'];
        }
        $rows = [];
        foreach (self::SOURCES as $key => [$label, $var]) {
            if (($sum[$key] ?? 0) > 0) {
                $rows[] = [$label, $sum[$key], $var, Unit::short($sum[$key])];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array{level: int, seconds: int}>  $own
     * @return list<array{0: string, 1: float, 2: string}>
     */
    private static function lastLevels(array $own, int $current): array
    {
        $rows = [];
        foreach (array_slice($own, -self::LAST_LEVELS) as $row) {
            $rows[] = ['Level '.$row['level'].($row['level'] === $current ? ', so far' : ''), round($row['seconds'] / 3600, 2), Format::duration($row['seconds'])];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    public static function combat(array $r, string $first): array
    {
        $deaths = $r['deaths'];
        $killers = array_map(fn (array $row): array => [$row['killer'], $row['deaths']], $deaths['killers']);
        $zones = array_map(fn (array $row): array => [$row['zone'] ?? 'Unknown', $row['deaths']], $deaths['zones']);
        $runs = array_sum(array_column($r['dungeons'], 'runs'));
        $clean = count(array_filter($r['dungeonRuns'], fn (array $run): bool => (int) $run['deaths'] === 0));
        $bosses = (int) $r['bossKills'] + (int) ($r['addonCounts']['bosses'] ?? 0);

        return [
            'killed' => array_map(fn (array $row): array => [$row['name'], $row['kills']], array_slice($r['killed'], 0, self::TOP)),
            'diedTitle' => $killers === [] ? 'Where '.$first.' died' : 'What killed '.$first,
            'died' => $killers === [] ? $zones : $killers,
            'deathLine' => $deaths['total'] > 0 ? Format::count($deaths['total'], 'death').($zones === [] ? '' : ' · '.$zones[0][1].' in '.$zones[0][0]) : null,
            'dungeons' => array_map(fn (array $row): array => [$row['name'], $row['runs'], Format::count($row['runs'], 'run')], array_slice($r['dungeons'], 0, self::TOP)),
            'dungeonLine' => $runs > 0 ? Format::count($runs, 'run').($bosses > 0 ? ' · '.Format::count($bosses, 'boss', 'bosses') : '').($clean > 0 ? ' · '.Format::count($clean, 'run').' with no deaths in the party' : '') : null,
        ];
    }

    /**
     * @param  array{character: Character, owner: bool, matches: list<array<string, mixed>>}|null  $answer  App\Actions\Public\CharacterBattlegrounds
     * @return list<array<string, mixed>>
     */
    public static function battlegrounds(?array $answer): array
    {
        return $answer === null || $answer['matches'] === [] ? [] : BattlegroundsPage::data($answer)['matches'];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    public static function loot(array $r): array
    {
        $items = $r['items'];
        $stack = [];
        foreach ($items['qualities'] as $quality => $count) {
            [$label, $var] = self::QUALITIES[(int) $quality] ?? ['Other', '--s-0'];
            $stack[] = [$label, (int) $count, $var];
        }
        $total = array_sum(array_column($stack, 1));
        $best = $items['best'][0] ?? null;
        $skills = $r['skills'];
        usort($skills, fn (array $a, array $b): int => [$b['rank'], $a['skill']] <=> [$a['rank'], $b['skill']]);

        return [
            'stack' => $stack,
            'line' => $total === 0 ? null : Format::number($total).' items',
            'best' => $best === null ? null : ['id' => $best['item_id'], 'name' => $best['name']],
            'professions' => array_map(fn (array $row): array => [$row['skill'], $row['rank'], Format::number($row['rank']).' of '.Format::number($row['cap'])], array_slice($skills, 0, 8)),
            'drops' => array_map(fn (array $row): array => [$row['name'], $row['quantity']], array_slice($items['best'], 0, self::TOP)),
            'gold' => $r['economy'] ? self::gold($r['gold']) : null,
        ];
    }

    /**
     * @param  list<array{at: string, balance: int}>|null  $gold
     * @return array<string, mixed>|null
     */
    private static function gold(?array $gold): ?array
    {
        if ($gold === null || count($gold) < 2) {
            return null;
        }
        $last = count($gold) - 1;
        $day = fn (array $row): string => gmdate('j M', (int) strtotime($row['at']));

        return [
            'spec' => ['points' => array_map(fn (array $row): float => round($row['balance'] / 10000, 1), $gold), 'unit' => 'g', 'name' => 'Gold held', 'days' => array_map($day, $gold), 'xlabels' => [[0, $day($gold[0])], [$last, $day($gold[$last])]]],
            'held' => Format::moneyText($gold[$last]['balance']),
            'change' => Format::moneyText($gold[$last]['balance'] - $gold[0]['balance'], true).' over the last '.Format::count($last + 1, 'session'),
        ];
    }
}
