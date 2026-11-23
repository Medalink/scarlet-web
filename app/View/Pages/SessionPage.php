<?php

namespace App\View\Pages;

use App\Models\Character;
use App\View\Format;
use App\View\Sc\ClassColor;
use App\View\Sc\RecapView;
use Illuminate\Support\HtmlString;

/** The data of /c/{name}/sessions/{start} (design/final/session-web.html) from the answer of App\Actions\Sessions\ShowSession. */
final class SessionPage
{
    private const array OWN_ROWS = [
        'xp_h' => 'XP an hour', 'kills_h' => 'Kills an hour', 'quests_h' => 'Quests an hour', 'miles_h' => 'Miles an hour', 'deaths' => 'Deaths', 'played' => 'Played',
    ];

    /**
     * @param  array{character: Character, recap: array<string, mixed>, owner: bool, economy: bool}  $answer
     * @return array<string, mixed>
     */
    public static function data(array $answer): array
    {
        $character = $answer['character'];
        $recap = $answer['recap'];
        $name = Format::characterName($character->name);
        $counters = $recap['counters'];
        $hours = max(1, $recap['played']) / 3600;
        $miles = RecapView::miles($recap);
        $links = $recap['links'];
        $base = '/c/'.rawurlencode($character->name);

        return [
            'name' => $name,
            'first' => explode(' ', $name)[0],
            'href' => $base,
            'color' => ClassColor::of($recap['class']),
            'line' => Format::character($recap['level'][1] ?: null, $character->race, $recap['class']),
            'recap' => $recap,
            'title' => $recap['title']['text'],
            'earlier' => $links['earlier'] === null ? null : $base.'/sessions/'.$links['earlier'],
            'earlierAt' => $links['earlier'],
            'later' => $links['later'] === null ? null : $base.'/sessions/'.$links['later'],
            'map' => '/map?character='.rawurlencode($character->name),
            'tiles' => self::tiles($recap, $counters, $hours, $miles, $answer['economy']),
            'distance' => self::distance($counters),
            'own' => self::own($recap['own'] ?? null),
            'ownCount' => $recap['own']['n'] ?? 0,
            'say' => self::say($recap['compare']['sentence'] ?? null),
            'peers' => self::peers($recap['compare'] ?? null),
            ...SessionBlocks::data($recap, $counters, $hours, $miles),
        ];
    }

    /**
     * @param  array<string, mixed>  $recap
     * @param  array<string, int>  $counters
     * @return list<array{label: string, value: mixed, unit?: string, sub: string, best: bool}>
     */
    private static function tiles(array $recap, array $counters, float $hours, float $miles, bool $economy): array
    {
        $bests = $recap['bests'];
        $xp = $counters['xp'] ?? array_sum($recap['xp']);
        $kills = $counters['kills'] ?? 0;
        $quests = $counters['quests'] ?? 0;
        $third = $economy
            ? ['label' => 'Gold', 'value' => Format::money($counters['earned'] ?? 0), 'sub' => ($counters['spent'] ?? 0) > 0 ? 'spent '.Format::moneyText($counters['spent']) : 'earned', 'best' => in_array('earned', $bests, true), 'n' => $counters['earned'] ?? 0]
            : ['label' => 'Quests', 'value' => Format::number($quests), 'sub' => self::rate($quests, $hours).(($counters['abandoned'] ?? 0) > 0 ? ' · '.$counters['abandoned'].' abandoned' : ''), 'best' => in_array('quests', $bests, true), 'n' => $quests];
        $tiles = [
            ['label' => 'Experience', 'value' => Format::number($xp), 'sub' => self::rate($xp, $hours), 'best' => in_array('xp', $bests, true) || in_array('xp_h', $bests, true), 'n' => $xp],
            ['label' => 'Kills', 'value' => Format::number($kills), 'sub' => self::rate($kills, $hours), 'best' => in_array('kills', $bests, true), 'n' => $kills],
            $third,
            ['label' => 'Distance', 'value' => Format::number($miles, 1), 'unit' => 'mi', 'sub' => self::distanceLine($counters), 'best' => in_array('distance', $bests, true), 'n' => $miles],
        ];

        return array_values(array_filter($tiles, fn (array $tile): bool => $tile['n'] > 0));
    }

    private static function say(?string $sentence): ?HtmlString
    {
        return $sentence === null ? null : new HtmlString((string) preg_replace('/(\d+% of sessions)/', '<b>$1</b>', e($sentence)));
    }

    /**
     * @param  array{bracket: array{0: int, 1: int}, sessions: int, metrics: array<string, array<string, int|float>>}|null  $compare
     * @return array{heading: string, note: string, rows: list<array{0: string, 1: array<string, float|int|string>, 2: string}>}|null
     */
    private static function peers(?array $compare): ?array
    {
        if ($compare === null) {
            return null;
        }
        $rows = [];
        foreach (['xp_h' => 'XP an hour', 'kills_h' => 'Kills an hour', 'quests_h' => 'Quests an hour'] as $key => $label) {
            $m = $compare['metrics'][$key] ?? null;
            if ($m === null) {
                continue;
            }
            $lo = min($m['p10'], $m['you']);
            $hi = max($m['p90'], $m['you']);
            $rows[] = [$label, [...$m, 'lo' => $lo, 'hi' => $hi > $lo ? $hi : $lo + 1, 'youText' => Format::number($m['you'], $m['you'] < 10 ? 1 : 0)], $m['percent'].'%'];
        }

        return [
            'heading' => 'Against sessions at levels '.$compare['bracket'][0].' to '.$compare['bracket'][1],
            'note' => 'Middle half of '.Format::number($compare['sessions']).' sessions',
            'rows' => $rows,
        ];
    }

    private static function rate(int|float $n, float $hours): string
    {
        $per = $n / $hours;

        return Format::number($per, $per < 10 ? 1 : 0).' an hour';
    }

    /** @param  array<string, int>  $counters */
    private static function distanceLine(array $counters): string
    {
        $parts = [];
        foreach ([['On foot', ($counters['foot'] ?? 0) + ($counters['walk'] ?? 0)], ['mounted', $counters['mount'] ?? 0], ['flown', $counters['fly'] ?? 0], ['swum', $counters['swim'] ?? 0]] as [$label, $yards]) {
            if ($yards > 0) {
                $parts[] = Format::number($yards / 1760, 1).' '.($label === 'On foot' ? 'on foot' : $label);
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, int>  $counters
     * @return list<array{0: string, 1: float, 2: string, 3: string}>
     */
    private static function distance(array $counters): array
    {
        $rows = [];
        foreach ([['Swimming', $counters['swim'] ?? 0, '--m-swim'], ['On foot', ($counters['foot'] ?? 0) + ($counters['walk'] ?? 0), '--m-foot'], ['Mounted', $counters['mount'] ?? 0, '--m-mount'], ['Flying', $counters['fly'] ?? 0, '--m-flight']] as [$label, $yards, $var]) {
            if ($yards > 0) {
                $rows[] = [$label, $yards / 1760, $var, Format::number($yards / 1760, 1).' mi'];
            }
        }

        return $rows;
    }

    /**
     * @param  array{n: int, metrics: array<string, array<string, int|float>>}|null  $own
     * @return list<array{0: string, 1: array<string, float|int|string>, 2: string}>
     */
    private static function own(?array $own): array
    {
        $rows = [];
        foreach (self::OWN_ROWS as $key => $label) {
            $m = $own['metrics'][$key] ?? null;
            if ($m === null || ($m['you'] == 0 && $m['p90'] == 0)) {
                continue;
            }
            $text = match ($key) {
                'played' => Format::duration($m['you']),
                'deaths' => Format::number($m['you']),
                'xp_h', 'kills_h' => Format::number($m['you']),
                default => Format::number($m['you'], 1),
            };
            $lo = min($m['p10'], $m['you']);
            $hi = max($m['p90'], $m['you']);
            $rows[] = [$label, [...$m, 'lo' => $lo, 'hi' => $hi > $lo ? $hi : $lo + 1, 'youText' => $text], $text];
        }

        return $rows;
    }
}
