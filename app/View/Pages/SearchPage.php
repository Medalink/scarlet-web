<?php

namespace App\View\Pages;

use App\Support\Cursor;
use App\Support\SearchKind;
use App\Support\SearchMatch;
use App\View\Format;
use App\View\ItemTypes;
use App\View\Marks;

/** The data of docs/comps/search.html from the answer of App\Actions\Public\Search. */
final class SearchPage
{
    /** Hits shown per kind when every kind is shown. */
    public const int PER_KIND = 5;

    /** Hits shown on the page of one kind. */
    public const int PAGE = 25;

    /** The key of the group of the site's own pages. */
    public const string PAGES = 'pages';

    /** The glyph of the group of the site's own pages. */
    private const string PAGES_ICON = 'distance';

    /**
     * @param  array<string, array{count: int, hits: list<array<string, mixed>>}>  $answer
     * @param  list<array{title: string, path: string, meta: string, match: string}>  $pages
     * @return array<string, mixed>
     */
    public static function data(array $answer, string $term, ?SearchKind $kind, int $offset, array $pages = []): array
    {
        $pages = $kind === null ? array_slice($pages, 0, self::PER_KIND) : [];
        $total = array_sum(array_column($answer, 'count')) + count($pages);
        $link = fn (?SearchKind $to, ?string $cursor = null): string => '/search?'.http_build_query(array_filter(['q' => $term, 'kind' => $to?->value, 'cursor' => $cursor]));

        $groups = [];
        $prev = null;
        $next = null;
        foreach ($answer as $name => $group) {
            $each = SearchKind::from($name);
            $hits = $group['hits'];
            if ($kind !== null) {
                [$hits, $cursor] = Cursor::page($hits, $offset, self::PAGE);
                $prevOffset = max(0, $offset - self::PAGE);
                $prev = $offset > 0 ? $link($each, $prevOffset > 0 ? Cursor::encode($prevOffset) : null) : null;
                $next = $cursor === null ? null : $link($each, $cursor);
            }
            $groups[$name] = [
                'title' => $each->label(),
                'icon' => self::icon($each),
                'count' => Format::number($group['count']),
                'hits' => array_map(fn (array $hit): array => self::hit($each, $hit, $term), $hits),
                'more' => $kind === null && $group['count'] > count($hits) ? ['href' => $link($each), 'label' => self::moreLabel($each, $group['count'] - count($hits))] : null,
            ];
        }
        if ($pages !== []) {
            $groups[self::PAGES] = ['title' => 'Pages', 'icon' => self::PAGES_ICON, 'count' => Format::number(count($pages)), 'hits' => array_map(fn (array $page): array => self::page($page, $term), $pages), 'more' => null];
        }
        $shown = $kind === null ? array_merge(...array_column($groups, 'hits')) : ($groups[$kind->value]['hits'] ?? []);

        return [
            'term' => $term,
            'kind' => $kind,
            'sub' => $total === 0
                ? 'No results for "'.$term.'". Characters appear only when their owner made them public.'
                : Format::number($total).($total === 1 ? ' result' : ' results').' for "'.$term.'". Characters appear only when their owner made them public.',
            'kinds' => [['label' => 'All '.Format::number($total), 'href' => $link(null), 'on' => $kind === null], ...array_map(
                fn (SearchKind $each): array => ['label' => $each->label().' '.Format::number($answer[$each->value]['count'] ?? 0), 'href' => $link($each), 'on' => $kind === $each],
                SearchKind::cases(),
            )],
            'best' => $kind === null ? self::best($answer, $pages, $term) : null,
            'close' => $shown !== [] && array_all($shown, fn (array $hit): bool => $hit['fuzzy']) ? 'No name contains "'.$term.'". Close spellings:' : null,
            'groups' => $groups,
            'prev' => $prev,
            'next' => $next,
            'empty' => $total === 0,
        ];
    }

    /**
     * What players search for, as groups of result lines: the rows of App\Actions\Public\SearchStart, a row with no
     * name left out and a group with no row left out.
     *
     * @param  array<string, list<array<string, mixed>>>  $read
     * @return list<array<string, mixed>>
     */
    public static function start(array $read): array
    {
        $groups = [];
        foreach ([
            [SearchKind::Creatures, 'Most killed creatures', 'npc_id', 'name', 'kills', 'kill'],
            [SearchKind::Zones, 'Zones with the most deaths', 'zone_id', 'zone', 'deaths', 'death'],
            [SearchKind::Quests, 'Most finished quests', 'quest_id', 'name', 'turn_ins', 'turn-in'],
        ] as [$each, $title, $id, $name, $number, $noun]) {
            $hits = [];
            foreach ($read[$each->value] ?? [] as $row) {
                if (! is_string($row[$name] ?? null) || $row[$name] === '') {
                    continue;
                }
                $hits[] = ['href' => $each->path().$row[$id], 'title' => $row[$name], 'meta' => '', 'value' => Format::count($row[$number], $noun), 'quality' => null, 'class' => null, 'marks' => Marks::plain($row[$name]), 'fuzzy' => false];
            }
            if ($hits !== []) {
                $groups[] = ['title' => $title, 'icon' => self::icon($each), 'count' => '', 'hits' => $hits, 'more' => null];
            }
        }

        return $groups;
    }

    /**
     * The best hit overall: the best tier of match, and inside a tier the first hit of the first kind that has one,
     * the site's own pages last. It is a result line with the glyph and the noun of its kind.
     *
     * @param  array<string, array{count: int, hits: list<array<string, mixed>>}>  $answer
     * @param  list<array{title: string, path: string, meta: string, match: string}>  $pages
     * @return array<string, mixed>|null
     */
    private static function best(array $answer, array $pages, string $term): ?array
    {
        $best = null;
        $rank = PHP_INT_MAX;
        foreach ($answer as $name => $group) {
            $each = SearchKind::from($name);
            foreach ($group['hits'] as $hit) {
                if (self::tier($hit)->rank() < $rank) {
                    $rank = self::tier($hit)->rank();
                    $best = self::hit($each, $hit, $term) + ['icon' => self::icon($each), 'noun' => self::noun($each)];
                }
            }
        }
        foreach ($pages as $page) {
            if (self::tier($page)->rank() < $rank) {
                $rank = self::tier($page)->rank();
                $best = self::page($page, $term) + ['icon' => self::PAGES_ICON, 'noun' => 'Page'];
            }
        }

        return $best;
    }

    /**
     * One line for a page of the site.
     *
     * @param  array{title: string, path: string, meta: string, match: string}  $page
     * @return array<string, mixed>
     */
    public static function page(array $page, string $term): array
    {
        $fuzzy = self::tier($page) === SearchMatch::Fuzzy;

        return ['href' => $page['path'], 'title' => $page['title'], 'meta' => $page['meta'], 'value' => '', 'quality' => null, 'class' => null, 'marks' => $fuzzy ? Marks::plain($page['title']) : Marks::cut($page['title'], $term), 'fuzzy' => $fuzzy];
    }

    /**
     * One result line. `$term` is the text searched for, which the title's marks follow; without it nothing is marked.
     *
     * @param  array<string, mixed>  $hit
     * @return array<string, mixed>
     */
    public static function hit(SearchKind $kind, array $hit, string $term = ''): array
    {
        $fuzzy = self::tier($hit) === SearchMatch::Fuzzy;
        [$name, $marked] = $kind === SearchKind::Characters
            ? [Format::characterName((string) $hit['name']), str_replace('-', ' ', $term)]
            : [(string) $hit['name'], $term];
        $line = ['href' => $hit['path'], 'title' => $name, 'quality' => null, 'marks' => $fuzzy ? Marks::plain($name) : Marks::cut($name, $marked), 'fuzzy' => $fuzzy];

        return $line + match ($kind) {
            SearchKind::Characters => [
                'class' => ($hit['class'] ?? null) === null || $hit['class'] === '' ? null : strtoupper((string) $hit['class']),
                'meta' => trim(Format::character($hit['level'], $hit['race'], $hit['class']).(isset($hit['ruleset_label']) ? ' · '.$hit['ruleset_label'] : '')),
                'value' => $hit['played_seconds'] === null ? Format::duration($hit['recorded_seconds']).' recorded' : Format::duration($hit['played_seconds']).' played',
            ],
            SearchKind::Creatures => [
                'meta' => self::creatureMeta($hit),
                'value' => $hit['kills'] === null ? '' : Format::count($hit['kills'], 'kill'),
            ],
            SearchKind::Items => [
                'quality' => ItemTypes::quality($hit['quality'] ?? null),
                'meta' => trim((($hit['item_level'] ?? null) ? 'Item level '.$hit['item_level'].' ' : '').ItemTypes::describe($hit['class_id'] ?? null, $hit['subclass_id'] ?? null, $hit['equip_loc'] ?? null)),
                'value' => is_numeric($hit['sources'] ?? null) ? 'Drops from '.Format::count($hit['sources'], 'source') : '',
            ],
            SearchKind::Objects => [
                'meta' => 'Object',
                'value' => is_numeric($hit['opened'] ?? null) ? Format::count($hit['opened'], 'opening') : '',
            ],
            SearchKind::Zones => [
                'meta' => is_numeric($hit['deaths'] ?? null) ? Format::count($hit['deaths'], 'death') : 'No play recorded',
                'value' => is_numeric($hit['seconds'] ?? null) ? Format::count(round($hit['seconds'] / 3600), 'hour') : '',
            ],
            SearchKind::Quests => [
                'meta' => 'Quest',
                'value' => is_numeric($hit['turn_ins'] ?? null) ? Format::count($hit['turn_ins'], 'turn-in') : '',
            ],
        };
    }

    /**
     * How a hit matched; a hit from before the tiers counts as a name that contains the text.
     *
     * @param  array<string, mixed>  $hit
     */
    private static function tier(array $hit): SearchMatch
    {
        return SearchMatch::tryFrom((string) ($hit['match'] ?? '')) ?? SearchMatch::Contains;
    }

    private static function moreLabel(SearchKind $kind, int $rest): string
    {
        return Format::number($rest).' more '.strtolower($rest === 1 ? self::noun($kind) : $kind->label());
    }

    private static function noun(SearchKind $kind): string
    {
        return match ($kind) {
            SearchKind::Characters => 'Character',
            SearchKind::Creatures => 'Creature',
            SearchKind::Items => 'Item',
            SearchKind::Objects => 'Object',
            SearchKind::Zones => 'Zone',
            SearchKind::Quests => 'Quest',
        };
    }

    /** The glyph of a group heading, a name of resources/views/components/icon.blade.php. */
    private static function icon(SearchKind $kind): string
    {
        return match ($kind) {
            SearchKind::Characters => 'characters',
            SearchKind::Creatures => 'creature',
            SearchKind::Items => 'item',
            SearchKind::Objects => 'gather',
            SearchKind::Zones => 'pin',
            SearchKind::Quests => 'quests',
        };
    }

    /** @param  array<string, mixed>  $hit */
    private static function creatureMeta(array $hit): string
    {
        $low = $hit['level_min'] ?? null;
        $high = $hit['level_max'] ?? null;
        $level = Format::creatureLevel($low, $high);

        return trim($level.($hit['type'] ?? null ? ' '.strtolower((string) $hit['type']) : ''), ' ') ?: 'Creature';
    }
}
