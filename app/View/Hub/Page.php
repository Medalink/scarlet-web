<?php

namespace App\View\Hub;

use App\View\Format;
use App\View\Pages\StatsPage;
use App\View\Sc\ClassColor;

/** The data of / (design/final/hub-web.html) from the bundle of App\Actions\Public\ShowHub. */
final class Page
{
    private const int LEVELS = 60;

    private const int TOP_CLASSES = 5;

    private const string DEADLY = '#ff2d55';

    private const string DEATH_BAR = 'rgba(255,45,85,0.55)';

    /**
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    public static function data(array $b): array
    {
        $totals = StatsPage::totals($b['totals']);
        $characters = $totals['characters'];
        $reached = array_column($b['reached'], 'characters', 'level');
        $cap = (int) ($reached[self::LEVELS] ?? 0);
        $curve = self::curve($b['curve']);

        return [
            'headline' => ($characters === null ? 'WoW Forever characters' : Format::number((int) $characters).' WoW Forever '.((int) $characters === 1 ? 'character' : 'characters')).', recorded by their players',
            'tiles' => array_values(array_filter([
                $totals['played_seconds'] === null ? null : ['label' => 'Hours recorded', 'value' => Format::number(round($totals['played_seconds'] / 3600)), 'unit' => null, 'sub' => null],
                $totals['kills'] === null ? null : ['label' => 'Kills', 'value' => self::short((float) $totals['kills'])[0], 'unit' => self::short((float) $totals['kills'])[1], 'sub' => null],
                $characters === null ? null : ['label' => 'Characters at level '.self::LEVELS, 'value' => Format::number($cap), 'unit' => null, 'sub' => $characters > 0 ? Format::percent($cap / $characters).' of all' : null],
                $b['feats']['tiers'] > 0 ? ['label' => 'Feat tiers earned', 'value' => Format::number($b['feats']['tiers']), 'unit' => null, 'sub' => Format::number($b['feats']['week']).' this week'] : null,
            ])),
            'curve' => $curve,
            'deaths' => self::deaths($b['deaths'], $b['played']),
            'classes' => self::classes($b['roster']['classes']),
            'directory' => self::directory($b),
            'fastest' => self::podium($b['fastest']),
            'clean' => self::podium($b['clean']),
            'heat' => self::heat($b['weekdays']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{spec: array<string, mixed>, sentence: string|null}|null
     */
    private static function curve(array $rows): ?array
    {
        $by = array_column($rows, null, 'level');
        if (count($by) < 10) {
            return null;
        }
        $fast = $median = $lo = $hi = [];
        $last = ['p10' => 0.0, 'median' => 0.0, 'q1' => 0.0, 'q3' => 0.0];
        for ($n = 1; $n <= self::LEVELS; $n++) {
            $row = $by[$n] ?? null;
            $last = $row === null ? $last : ['p10' => (float) $row['p10'], 'median' => (float) $row['median'], 'q1' => (float) $row['q1'], 'q3' => (float) $row['q3']];
            $fast[] = round($last['p10'] / 3600, 1);
            $median[] = round($last['median'] / 3600, 1);
            $lo[] = round($last['q1'] / 3600, 1);
            $hi[] = round($last['q3'] / 3600, 1);
        }
        $ymax = (int) (ceil(max($hi) / 50) * 50);
        $top = $by[self::LEVELS] ?? null;

        return [
            'spec' => ['you' => $fast, 'median' => $median, 'lo' => $lo, 'hi' => $hi, 'ymax' => max(50, $ymax), 'unit' => 'h', 'youLabel' => '', 'youName' => 'Fastest tenth', 'medianLabel' => 'Median', 'xlabels' => [[0, '1'], [9, '10'], [19, '20'], [29, '30'], [39, '40'], [49, '50'], [59, '60']]],
            'sentence' => $top === null ? null : 'The median character reaches '.self::LEVELS.' after <b>'.Format::duration((float) $top['median']).'</b> played. The fastest tenth need under '.Format::duration((float) $top['p10']).'.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $deaths
     * @param  list<array<string, mixed>>  $played
     * @return array{spec: array<string, mixed>, sentence: string}|null
     */
    private static function deaths(array $deaths, array $played): ?array
    {
        $rates = array_column(StatsPage::deathRates($deaths, $played), 'rate', 'level');
        if (count($rates) < 5) {
            return null;
        }
        $worst = (int) array_search(max($rates), $rates, true);
        $rows = [];
        for ($n = 1; $n <= self::LEVELS; $n++) {
            $rows[] = [(string) $n, round($rates[$n] ?? 0, 2), null, $n === $worst ? self::DEADLY : self::DEATH_BAR];
        }

        return [
            'spec' => ['xname' => 'Level', 'name' => 'Deaths an hour', 'ymax' => round(max(0.1, ceil(max($rates) * 10) / 10), 1), 'every' => 10, 'rows' => $rows],
            'sentence' => 'Level '.$worst.' is the deadliest: <b>'.Format::number($rates[$worst], 2).' deaths an hour</b>.',
        ];
    }

    /**
     * @param  list<array{class: string, characters: int}>  $classes
     * @return list<array{0: string, 1: int, 2: string}>
     */
    private static function classes(array $classes): array
    {
        $total = max(1, array_sum(array_column($classes, 'characters')));
        usort($classes, fn (array $a, array $b): int => $b['characters'] <=> $a['characters']);

        return array_map(fn (array $row): array => [Format::className($row['class']), (int) $row['characters'], Format::percent($row['characters'] / $total)], array_slice($classes, 0, self::TOP_CLASSES));
    }

    /**
     * @param  array<string, mixed>  $b
     * @return list<array<string, mixed>>
     */
    private static function directory(array $b): array
    {
        $lead = $b['fastest'][0] ?? null;
        $feats = $b['feats'];

        return [
            ['name' => 'Stats', 'href' => '/stats', 'fact' => 'Levelling, danger, dungeons, gold and play time, by ruleset.', 'bold' => $b['rulesets'] > 1 ? Format::count($b['rulesets'], 'ruleset').' compared.' : null, 'aside' => null],
            ['name' => 'Feats', 'href' => '/feats', 'fact' => Format::number($feats['kinds']).' feats in five tiers.', 'bold' => $feats['week'] > 0 ? Format::count($feats['week'], 'tier').' earned this week.' : null, 'aside' => null],
            ['name' => 'Leaderboards', 'href' => '/leaderboards', 'fact' => Format::number($b['boards']).' boards.', 'bold' => $lead === null ? null : 'Fastest to 60: '.$lead['value'].'.', 'aside' => null],
            ['name' => 'Map', 'href' => '/map', 'fact' => 'Every walked path, kill and death on one map.', 'bold' => null, 'aside' => null],
            ['name' => 'Loot', 'href' => '/loot', 'fact' => 'Drop rates for creatures and objects, with 95% ranges and loot rules.', 'bold' => null, 'aside' => null],
            ['name' => 'Professions', 'href' => '/professions/fishing', 'fact' => 'Fishing, mining, herbalism, cooking and the other crafts, with what each one yields.', 'bold' => null, 'aside' => null],
            ['name' => 'Talents', 'href' => '/talents', 'fact' => 'Every spec and who plays it.', 'bold' => null, 'aside' => null],
            ['name' => 'Camps', 'href' => '/camps', 'fact' => 'Campfires, tents and chairs, and how deaths split with and without them.', 'bold' => null, 'aside' => null],
        ];
    }

    /**
     * @param  list<array{name: string, class: string, value: string}>  $rows
     * @return list<array{n: int, tone: string, color: string, name: string, href: string, value: string}>
     */
    private static function podium(array $rows): array
    {
        return array_map(fn (array $row, int $i): array => ['n' => $i + 1, 'tone' => ['sc-g', 'sc-s', 'sc-c'][$i], 'color' => ClassColor::of($row['class']), 'name' => Format::characterName($row['name']), 'href' => '/c/'.rawurlencode($row['name']), 'value' => $row['value']], $rows, array_keys($rows));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{level: int, tip: string}>|null
     */
    private static function heat(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }
        $names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $seconds = array_fill(0, 168, 0.0);
        foreach ($rows as $row) {
            $seconds[((int) $row['weekday'] + 6) % 7 * 24 + (int) $row['hour']] = (float) $row['seconds'];
        }
        $top = max($seconds);
        $cells = [];
        foreach ($seconds as $i => $value) {
            $cells[] = ['level' => $top <= 0 || $value <= 0 ? 0 : max(1, (int) ceil($value / $top * 5)), 'tip' => $names[intdiv($i, 24)].' '.str_pad((string) ($i % 24), 2, '0', STR_PAD_LEFT).':00|'.($value > 0 ? Format::hours($value).' played' : 'Not played')];
        }

        return $cells;
    }

    /** @return array{0: string, 1: string|null} */
    private static function short(float $value): array
    {
        if ($value >= 1_000_000) {
            return [Format::number($value / 1_000_000, 1), 'million'];
        }

        return [Format::number($value), null];
    }
}
