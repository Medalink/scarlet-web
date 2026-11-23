<?php

namespace App\View\Account;

use App\View\Format;
use App\View\Pages\CharacterStandings;
use App\View\Pages\FeatsPage;
use App\View\Sc\ClassColor;
use App\View\Sc\Compare;
use Illuminate\Support\Carbon;

/** The data of /account (design/final/account-web.html) from the bundle of App\Actions\Account\ShowAccountHome. */
final class Home
{
    private const int LEVELS = 60;

    private const int MEDIAN_FROM = 2;

    private const int REACH = 3;

    private const int PLACES = 4;

    private const string PARTIAL = 'rgba(255,45,85,0.45)';

    /**
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    public static function data(array $b): array
    {
        $characters = $b['characters'];
        $by = array_column($characters, null, 'id');
        $seconds = array_sum(array_map(fn (array $row): int => (int) ($row['played_seconds'] ?? $row['recorded_seconds']), $characters));
        $main = $b['featured'] === null ? null : $by[$b['featured']];

        return [
            'title' => Format::count(count($characters), 'character').', '.Format::count((int) round($seconds / 3600), 'hour').' recorded',
            'empty' => $characters === [],
            'latest' => self::latest($b['latest'], $by),
            'week' => Week::tiles($b, $by),
            'rows' => self::rows($b),
            'more' => count($characters) > count($b['shown']) ? Format::count(count($characters) - count($b['shown']), 'more character') : null,
            'play' => Play::days($characters, $b['days']),
            'gold' => Play::gold($characters, $b['gold'], $b['days']),
            'compare' => $main === null ? null : self::compare($b['rates'], $main),
            'levels' => $main === null ? null : self::levels($b['levelTimes'], $main),
            'reach' => self::reach($b, $by),
            'places' => self::places($b, $by),
            'heat' => Play::heat($b['buckets']),
            'link' => self::link($b),
        ];
    }

    /**
     * @param  array{character_id: int, recap: array<string, mixed>|null}|null  $latest
     * @param  array<int, array<string, mixed>>  $by
     * @return array<string, mixed>|null
     */
    private static function latest(?array $latest, array $by): ?array
    {
        if ($latest === null || $latest['recap'] === null) {
            return null;
        }
        $row = $by[$latest['character_id']];

        return ['recap' => $latest['recap'], 'name' => Format::characterName($row['name']), 'color' => ClassColor::of($row['class']), 'href' => '/c/'.rawurlencode($row['name']).'/sessions/'.$latest['recap']['start']];
    }

    /**
     * @param  array<string, mixed>  $b
     * @return list<array<string, mixed>>
     */
    private static function rows(array $b): array
    {
        $rows = [];
        $max = FeatsPage::scoreMax();
        foreach ($b['shown'] as $c) {
            $summary = $b['feats'][$c['id']]['character'];
            $place = CharacterStandings::rows($b['places'][$c['id']])[0] ?? null;
            $level = (int) $c['level'];
            $rows[] = [
                'href' => '/c/'.rawurlencode($c['name']),
                'color' => ClassColor::of($c['class']),
                'name' => Format::characterName($c['name']),
                'line' => trim(Format::raceName($c['race']).' '.Format::className($c['class'])).' · '.Format::hours($c['played_seconds'] ?? $c['recorded_seconds'], false).' played',
                'level' => $level,
                'width' => round(min(1, $level / self::LEVELS) * 100, 1),
                'ring' => $max === 0 ? 0 : round($summary['score'] / $max, 3),
                'feats' => (int) $summary['held'],
                'seen' => $c['last_seen_at'] === null ? 'Nothing recorded yet' : 'Last played '.Carbon::parse($c['last_seen_at'])->format('j M'),
                'place' => $place === null ? ($c['is_public'] ? null : 'Private page') : Format::ordinal($place['place']).' of '.$place['of'].' on '.$place['title'],
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>|null  $rates
     * @param  array<string, mixed>  $main
     * @return array<string, mixed>|null
     */
    private static function compare(?array $rates, array $main): ?array
    {
        if ($rates === null) {
            return null;
        }
        $name = Format::characterName($main['name']);
        $class = $rates['class'];

        return [
            'heading' => $name.' against other '.$class.'s, levels '.$rates['bracket'][0].' to '.$rates['bracket'][1],
            'name' => $name,
            'key' => $rates['n'] >= Compare::MIN_GROUP ? 'Middle half of '.Format::number($rates['n']).' '.$class.'s' : null,
            'rows' => $rates['rows'],
            'n' => $rates['n'],
            'sentence' => $rates['sentence'],
            'color' => ClassColor::of($main['class']),
        ];
    }

    /**
     * @param  array{character: list<array{level: int, seconds: int}>, ruleset: list<array<string, mixed>>}|null  $times
     * @param  array<string, mixed>  $main
     * @return array<string, mixed>|null
     */
    private static function levels(?array $times, array $main): ?array
    {
        if ($times === null || count($times['character']) < 2) {
            return null;
        }
        $own = $times['character'];
        $medians = [];
        foreach ($times['ruleset'] as $row) {
            if ((int) $row['completed'] >= self::MEDIAN_FROM) {
                $medians[(int) $row['level']] = (float) $row['median'] / 3600;
            }
        }
        $current = (int) $main['level'];
        $rows = [];
        foreach ($own as $row) {
            $median = isset($medians[$row['level']]) ? round($medians[$row['level']], 1) : null;
            $rows[] = [(string) $row['level'], round($row['seconds'] / 3600, 1), $median, $row['level'] === $current ? self::PARTIAL : null];
        }
        $name = Format::characterName($main['name']);

        return [
            'name' => $name,
            'cols' => ['rows' => $rows, 'unit' => 'h', 'xname' => 'Level', 'name' => $name, 'fmt' => '#h', 'every' => 5, 'ymax' => max(1, (int) ceil(max([0.0, ...array_map(fn (array $row): float => max($row[1], $row[2] ?? 0), $rows)])))],
            'sentence' => self::slowest($own, $medians, $current),
        ];
    }

    /**
     * @param  list<array{level: int, seconds: int}>  $own
     * @param  array<int, float>  $medians
     */
    private static function slowest(array $own, array $medians, int $current): ?string
    {
        $worst = null;
        foreach ($own as $row) {
            $gap = isset($medians[$row['level']]) ? $row['seconds'] / 3600 - $medians[$row['level']] : 0;
            if ($row['level'] !== $current && $gap > ($worst['gap'] ?? 0)) {
                $worst = ['level' => $row['level'], 'seconds' => $row['seconds'], 'gap' => $gap];
            }
        }

        return $worst === null ? null : 'Level '.$worst['level'].' took '.Format::hours($worst['seconds']).'; the median is '.Format::hours($medians[$worst['level']] * 3600).'.';
    }

    /**
     * @param  array<string, mixed>  $b
     * @param  array<int, array<string, mixed>>  $by
     * @return list<array<string, mixed>>
     */
    private static function reach(array $b, array $by): array
    {
        $all = [];
        foreach ($b['feats'] as $id => $hub) {
            foreach ($hub['closest'] as $feat) {
                $all[] = [$feat, $id];
            }
        }
        usort($all, fn (array $x, array $y): int => $y[0]['progress'] <=> $x[0]['progress']);

        return array_map(function (array $pair) use ($by): array {
            [$feat, $id] = $pair;
            $card = FeatsPage::closestCard($feat);

            return $card + ['name' => $card['name'], 'count' => Format::characterName($by[$id]['name']).' · '.$card['left'], 'new' => false];
        }, array_slice($all, 0, self::REACH));
    }

    /**
     * @param  array<string, mixed>  $b
     * @param  array<int, array<string, mixed>>  $by
     * @return list<array<string, mixed>>
     */
    private static function places(array $b, array $by): array
    {
        $all = [];
        foreach ($b['places'] as $id => $standings) {
            foreach (CharacterStandings::rows($standings) as $row) {
                $all[] = ['place' => $row['place'], 'ordinal' => Format::ordinal($row['place']), 'title' => $row['title'], 'href' => $row['href'], 'value' => $row['value'], 'of' => $row['of'], 'who' => Format::characterName($by[$id]['name'])];
            }
        }
        usort($all, fn (array $x, array $y): int => $x['place'] <=> $y['place']);

        return array_slice($all, 0, self::PLACES);
    }

    /**
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    private static function link(array $b): array
    {
        $live = array_values(array_filter($b['installs'], fn ($install): bool => ! $install->isRevoked()));
        if ($live === []) {
            return ['linked' => false, 'text' => 'No app is linked to this account. Open Scarlet on your PC and choose Link to my account.'];
        }
        $last = null;
        foreach ($live as $install) {
            $last = $last === null || ($install->last_seen_at !== null && $install->last_seen_at->gt($last)) ? $install->last_seen_at : $last;
        }
        $parts = ['Scarlet app linked on '.Format::count(count($live), 'computer'), $last === null ? 'nothing uploaded yet' : 'last upload '.$last->utc()->format('j M H:i').' UTC'];
        $private = $b['private'];
        if ($private !== []) {
            $parts[] = count($private) === 1 ? Format::characterName($private[0])."'s page is private" : Format::count(count($private), 'page').' are private';
        }

        return ['linked' => true, 'text' => implode(' · ', $parts)];
    }
}
