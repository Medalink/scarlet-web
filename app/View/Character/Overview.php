<?php

namespace App\View\Character;

use App\Support\ClassPage\ClassPage;
use App\View\Format;
use App\View\Pages\CharacterStandings;
use App\View\Sc\Compare;

/** The comparison block, the standings and the level curve of /c/{name}. */
final class Overview
{
    private const int CLASS_ROWS = 4;

    private const int PLACES = 3;

    private const int LEVELS = 60;

    /**
     * @param  array{rows: list<array{0: string, 1: array<string, float|int|string>, 2: string, 3: int}>, bracket: array{0: int, 1: int}, n: int, class: string, sentence: string|null}|null  $rates
     * @param  array<string, mixed>  $r
     * @param  array<string, mixed>  $feats  the summary of BuildCharacterFeats
     * @return array<string, mixed>
     */
    public static function data(?array $rates, ?ClassPage $class, array $r, array $feats, string $first): array
    {
        return [
            'compare' => self::compare($rates, $class, $first),
            'standings' => self::standings($r['standings'], $feats),
            'curve' => self::curve($r['pace'], $r['character']->level, $r['ruleset']['name'] ?? null, $first),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $rates
     * @return array<string, mixed>
     */
    private static function compare(?array $rates, ?ClassPage $class, string $first): array
    {
        $className = $rates['class'] ?? ($class === null ? '' : $class->name());
        $rows = $rates['rows'] ?? [];
        $classRows = [];
        $sentence = $rates['sentence'] ?? null;
        $comparison = $class?->comparison;
        if ($comparison !== null) {
            foreach (array_slice($comparison->standings, 0, self::CLASS_ROWS) as $standing) {
                $percent = max(0, min(100, $standing->percent));
                $classRows[] = [$standing->label, ['lo' => 0, 'hi' => 100, 'p10' => 10, 'p25' => 25, 'p50' => 50, 'p75' => 75, 'p90' => 90, 'you' => $percent, 'youText' => $standing->unit->figure($standing->value)], $standing->unit->figure($standing->value), $comparison->characters];
            }
            $best = null;
            foreach ($comparison->standings as $standing) {
                $best = $best === null || $standing->percent > $best->percent ? $standing : $best;
            }
            $sentence ??= $best === null || $best->percent < 55 ? null : $best->label.' '.$best->unit->figure($best->value).': more than '.$best->percent.'% of '.$comparison->of.' at these levels';
        }
        $bracket = $rates['bracket'] ?? ($comparison === null ? null : [$comparison->from, $comparison->to]);
        $n = $rates['n'] ?? ($comparison === null ? 0 : $comparison->characters);

        return [
            'heading' => $bracket === null ? 'Against other '.$className.'s' : 'Against other '.$className.'s, levels '.$bracket[0].' to '.$bracket[1],
            'rows' => $rows,
            'classRows' => $classRows,
            'n' => $n,
            'minimum' => Compare::MIN_GROUP,
            'name' => $first,
            'className' => $className,
            'key' => $n >= Compare::MIN_GROUP ? 'Middle half of '.Format::number($n).' '.$className.'s' : null,
            'sentence' => $sentence,
            'empty' => $rows === [] && $classRows === [] ? 'Not enough '.$className.'s at these levels yet.' : null,
        ];
    }

    /**
     * @param  list<array{board: string, title: string, place: int, total: int, value: int|float}>  $standings
     * @param  array<string, mixed>  $feats
     * @return list<array{place: string, title: string, note: string|null, value: string, href: string}>
     */
    private static function standings(array $standings, array $feats): array
    {
        $rows = array_map(fn (array $row): array => [
            'place' => Format::ordinal($row['place']), 'title' => $row['title'], 'note' => null, 'value' => $row['value'], 'href' => $row['href'],
        ], CharacterStandings::rows($standings));
        $score = $feats['place'] === null ? null : ['place' => Format::ordinal((int) $feats['place']), 'title' => 'Feat score', 'note' => null, 'value' => $feats['score'].' of '.$feats['max'], 'href' => '/leaderboards/feat-score'];
        $rows = array_slice($rows, 0, $score === null ? self::PLACES : self::PLACES - 1);
        if ($score !== null) {
            $rows[] = $score;
        }

        return $rows;
    }

    /**
     * @param  array{character: list<array{level: int, seconds: int}>, ruleset: list<array<string, mixed>>}  $pace
     * @return array<string, mixed>|null
     */
    private static function curve(array $pace, ?int $level, ?string $ruleset, string $first): ?array
    {
        $own = array_column($pace['character'], 'seconds', 'level');
        $curve = array_column($pace['ruleset'], null, 'level');
        if (count($own) < 3 || count($curve) < 10) {
            return null;
        }
        $median = $lo = $hi = [];
        $last = ['median' => 0.0, 'q1' => 0.0, 'q3' => 0.0];
        for ($n = 1; $n <= self::LEVELS; $n++) {
            $row = $curve[$n] ?? null;
            $last = $row === null ? $last : ['median' => (float) $row['median'], 'q1' => (float) $row['q1'], 'q3' => (float) $row['q3']];
            $median[] = round($last['median'] / 3600, 1);
            $lo[] = round($last['q1'] / 3600, 1);
            $hi[] = round($last['q3'] / 3600, 1);
        }
        $top = max(array_keys($own));
        $you = [];
        for ($n = 1; $n <= $top; $n++) {
            $you[] = round(($own[$n] ?? ($you === [] ? 0 : end($you) * 3600)) / 3600, 1);
        }
        $ymax = max($hi);
        $ymax = (int) (ceil($ymax / 100) * 100);
        $reached = (float) end($you);
        $typical = $median[min($top, self::LEVELS) - 1];
        $gap = (int) round(abs($typical - $reached));

        return [
            'spec' => ['you' => $you, 'median' => $median, 'lo' => $lo, 'hi' => $hi, 'ymax' => $ymax, 'unit' => 'h', 'youLabel' => '', 'youName' => $first, 'medianLabel' => '', 'medianName' => 'Median'.($ruleset === null ? '' : ' on '.$ruleset), 'xlabels' => [[0, '1'], [9, '10'], [19, '20'], [29, '30'], [39, '40'], [49, '50'], [59, '60']]],
            'ruleset' => $ruleset,
            'sentence' => 'Level '.($level ?? $top).' after '.Format::number($reached).' hours played. The median character needs '.Format::number($typical).($gap === 0 ? '.' : ': <b>'.$gap.' hours '.($reached <= $typical ? 'ahead' : 'behind').'</b>.'),
        ];
    }
}
