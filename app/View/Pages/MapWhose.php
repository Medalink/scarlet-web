<?php

namespace App\View\Pages;

use App\View\Format;

/** The switch of the world map between everyone's heat, the viewer's own characters and one character's trails. */
final class MapWhose
{
    /** More choices than this are offered in a drop-down instead of a segmented control. */
    private const int SEG_LIMIT = 4;

    public const string EVERYONE = 'Everyone';

    public const string ALL_OWN = 'All my characters';

    /**
     * The choices: Everyone, then for a signed-in viewer with more than one character all of them together, then each of
     * the viewer's characters, then the character on show when it is someone else's. Null when there is nothing to
     * switch to: a visitor on everyone's map.
     *
     * @param  list<string>  $own  the viewer's own character names
     * @param  string|null  $character  the character on show
     * @param  bool  $allOwn  whether all the viewer's characters are on show
     * @param  int|null  $zone  the zone the map is on, which every choice keeps
     * @return array{items: list<array{label: string, href: string, on: bool}>|null, pick: list<array{0: string, 1: string}>|null, selected: string}|null
     */
    public static function of(array $own, ?string $character, bool $allOwn, ?int $zone): ?array
    {
        if ($own === [] && $character === null) {
            return null;
        }
        $link = fn (string $path, array $query): string => $path.(($query = array_filter([...$query, 'zone' => $zone])) === [] ? '' : '?'.http_build_query($query));
        $names = $character === null || in_array($character, $own, true) ? $own : [...$own, $character];
        $items = [['label' => self::EVERYONE, 'href' => $link('/map', []), 'on' => $character === null && ! $allOwn]];
        if (count($own) > 1) {
            $items[] = ['label' => self::ALL_OWN, 'href' => $link('/account/map', []), 'on' => $allOwn];
        }
        $onlyOwn = $allOwn && count($own) === 1;
        foreach ($names as $name) {
            $items[] = ['label' => Format::characterName($name), 'href' => $link('/map', ['character' => $name]), 'on' => $name === $character || $onlyOwn];
        }
        $selected = array_values(array_filter($items, fn (array $item): bool => $item['on']))[0]['label'] ?? self::EVERYONE;
        $pick = count($items) > self::SEG_LIMIT;

        return [
            'items' => $pick ? null : $items,
            'pick' => $pick ? array_map(fn (array $item): array => [$item['label'], $item['href']], $items) : null,
            'selected' => $selected,
        ];
    }
}
