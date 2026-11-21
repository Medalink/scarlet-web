<?php

namespace App\View\Pages;

use App\Support\Cursor;
use App\Support\SearchKind;
use App\View\Format;
use App\View\ItemTypes;

/** The data of docs/comps/search.html from the answer of App\Actions\Public\Search. */
final class SearchPage
{
    /** Hits shown per kind when every kind is shown. */
    public const int PER_KIND = 5;

    /** Hits shown on the page of one kind. */
    public const int PAGE = 25;

    /**
     * @param  array<string, array{count: int, hits: list<array<string, mixed>>}>  $answer
     * @return array<string, mixed>
     */
    public static function data(array $answer, string $term, ?SearchKind $kind, int $offset): array
    {
        $total = array_sum(array_column($answer, 'count'));
        $link = fn (?SearchKind $to, ?string $cursor = null): string => '/search?'.http_build_query(array_filter(['q' => $term, 'kind' => $to?->value, 'cursor' => $cursor]));

        $groups = [];
        $next = null;
        foreach ($answer as $name => $group) {
            $each = SearchKind::from($name);
            $hits = $group['hits'];
            if ($kind !== null) {
                [$hits, $cursor] = Cursor::page($hits, $offset, self::PAGE);
                $next = $cursor === null ? null : $link($each, $cursor);
            }
            $groups[$name] = [
                'title' => $each->label(),
                'count' => Format::number($group['count']),
                'hits' => array_map(fn (array $hit): array => self::hit($each, $hit), $hits),
                'more' => $kind === null && $group['count'] > count($hits) ? ['href' => $link($each), 'label' => Format::number($group['count'] - count($hits)).' more '.strtolower($each->label())] : null,
            ];
        }

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
            'groups' => $groups,
            'next' => $next,
            'empty' => $total === 0,
        ];
    }

    /**
     * One result line.
     *
     * @param  array<string, mixed>  $hit
     * @return array<string, mixed>
     */
    public static function hit(SearchKind $kind, array $hit): array
    {
        $line = ['href' => $hit['path'], 'title' => $hit['name'], 'quality' => null];

        return $line + match ($kind) {
            SearchKind::Characters => [
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

    /** @param  array<string, mixed>  $hit */
    private static function creatureMeta(array $hit): string
    {
        $low = $hit['level_min'] ?? null;
        $high = $hit['level_max'] ?? null;
        $level = Format::creatureLevel($low, $high);

        return trim($level.($hit['type'] ?? null ? ' '.strtolower((string) $hit['type']) : ''), ' ') ?: 'Creature';
    }
}
