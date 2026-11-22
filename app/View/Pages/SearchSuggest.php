<?php

namespace App\View\Pages;

use App\Support\SearchKind;
use App\Support\SearchMatch;
use App\View\Format;

/** The answer of GET /search/suggest: the lines of the instant results panel, already formatted. */
final class SearchSuggest
{
    /** Hits taken from each kind. */
    public const int PER_KIND = 4;

    /** Lines the panel shows at most. */
    public const int LINES = 8;

    /** Site pages taken from the pages answer. */
    public const int PAGES_SHOWN = 3;

    private const string PAGE_GLYPH = 'distance';

    private const string RECENT_GLYPH = 'clock';

    /**
     * The best hit overall first (the best tier, then the order of the kinds), then the rest by kind. Every kind with a
     * hit gets a line before any kind gets a second one, up to the limit.
     *
     * @param  array<string, array{count: int, hits: list<array<string, mixed>>}>  $answer
     * @param  list<array{title: string, path: string, meta: string, match: string}>  $pages
     * @return array{term: string, lines: list<array<string, mixed>>, all: array{href: string, label: string}}
     */
    public static function data(array $answer, array $pages, string $term): array
    {
        $groups = [];
        $total = count(array_slice($pages, 0, SearchPage::PER_KIND));
        $capped = false;
        foreach (SearchKind::cases() as $kind) {
            $group = $answer[$kind->value] ?? ['count' => 0, 'hits' => []];
            $total += $group['count'];
            $capped = $capped || $group['count'] >= self::PER_KIND;
            $groups[] = array_map(fn (array $hit): array => ['rank' => self::rank($hit['match'] ?? null), 'line' => self::line($kind, $hit, $term)], array_slice($group['hits'], 0, self::PER_KIND));
        }
        $groups[] = array_map(fn (array $page): array => ['rank' => self::rank($page['match']), 'line' => self::pageLine($page, $term)], array_slice($pages, 0, self::PAGES_SHOWN));

        return [
            'term' => $term,
            'lines' => self::pick($groups),
            'all' => [
                'href' => $term === '' ? '/search' : '/search?'.http_build_query(['q' => $term]),
                'label' => match (true) {
                    $term === '' => 'Search',
                    $total === 0 => 'No results for "'.$term.'"',
                    $capped => 'See all results',
                    $total === 1 => 'See 1 result',
                    default => 'See all '.Format::count($total, 'result'),
                },
            ],
        ];
    }

    /**
     * The glyphs the panel draws: one per kind of line and the one of a recent search.
     *
     * @return list<string>
     */
    public static function glyphs(): array
    {
        return [...array_map(self::icon(...), SearchKind::cases()), self::PAGE_GLYPH, self::RECENT_GLYPH];
    }

    /**
     * @param  list<list<array{rank: int, line: array<string, mixed>}>>  $groups
     * @return list<array<string, mixed>>
     */
    private static function pick(array $groups): array
    {
        $best = null;
        foreach ($groups as $at => $items) {
            if ($items !== [] && ($best === null || $items[0]['rank'] < $groups[$best][0]['rank'])) {
                $best = $at;
            }
        }
        if ($best === null) {
            return [];
        }

        $first = $groups[$best][0];
        $groups[$best] = array_slice($groups[$best], 1);
        $taken = array_fill(0, count($groups), []);
        $count = 1;
        for ($round = 0; $count < self::LINES; $round++) {
            $added = false;
            foreach ($groups as $at => $items) {
                if ($count < self::LINES && isset($items[$round])) {
                    $taken[$at][] = $items[$round]['line'];
                    $count++;
                    $added = true;
                }
            }
            if (! $added) {
                break;
            }
        }

        return [$first['line'], ...array_merge(...$taken)];
    }

    private static function rank(mixed $match): int
    {
        return (is_string($match) ? SearchMatch::tryFrom($match) : null)?->rank() ?? SearchMatch::Contains->rank();
    }

    /**
     * @param  array<string, mixed>  $hit
     * @return array<string, mixed>
     */
    private static function line(SearchKind $kind, array $hit, string $term): array
    {
        $line = SearchPage::hit($kind, $hit, $term);

        return [
            'kind' => $kind->value,
            'icon' => self::icon($kind),
            'href' => $line['href'],
            'title' => $line['title'],
            'marks' => $line['marks'] ?? null,
            'meta' => $line['meta'],
            'value' => $line['value'],
            'quality' => $line['quality'] ?? null,
            'class' => $line['class'] ?? null,
        ];
    }

    /**
     * @param  array{title: string, path: string, meta: string, match: string}  $page
     * @return array<string, mixed>
     */
    private static function pageLine(array $page, string $term): array
    {
        $line = SearchPage::page($page, $term);

        return [
            'kind' => SearchPage::PAGES,
            'icon' => self::PAGE_GLYPH,
            'href' => $line['href'],
            'title' => $line['title'],
            'marks' => $line['marks'],
            'meta' => $line['meta'],
            'value' => $line['value'],
            'quality' => null,
            'class' => null,
        ];
    }

    private static function icon(SearchKind $kind): string
    {
        return match ($kind) {
            SearchKind::Characters => 'characters',
            SearchKind::Creatures => 'creature',
            SearchKind::Items => 'item',
            SearchKind::Zones => 'pin',
            SearchKind::Quests => 'quests',
        };
    }
}
