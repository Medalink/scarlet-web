<?php

namespace App\View\Pages;

use App\Support\LootRule;
use App\Support\RateFlag;
use App\Support\RateSample;
use App\Support\WorldLayer;
use App\View\Format;

/** The data of the object page (docs/comps/object.html) from the answer of App\Actions\Public\ObjectRecord. */
final class ObjectPage
{
    /**
     * @param  array<string, mixed>  $r  the answer of ObjectRecord
     * @return array<string, mixed>
     */
    public static function from(array $r): array
    {
        /** @var array{rule: string|null, unread: int, rules: list<array{rule: string, windows: int}>, rows: list<array<string, mixed>>} $table */
        $table = $r['drop_table'];
        /** @var list<array{id: int, name: string|null, windows: int}> $zones */
        $zones = $r['zones'];
        $chosen = $table['rule'];
        $base = '/objects/'.$r['id'];
        $query = fn (?string $rule): string => $rule === null ? $base : $base.'?'.http_build_query(['rule' => $rule]);
        $name = is_string($r['name']) ? $r['name'] : 'Object '.$r['id'];

        return [
            'name' => $name,
            'sub' => self::sub($r, $zones, $table['rows']),
            'figures' => [
                ['label' => 'Opened', 'value' => Format::number($r['windows'])],
                ['label' => 'Items', 'value' => Format::number($r['items'])],
                ['label' => 'Characters', 'value' => Format::number($r['characters'])],
                ['label' => 'Zones', 'value' => Format::number(count($zones))],
            ],
            'rules' => $table['rules'] === [] ? [] : [
                ['label' => 'All rules', 'href' => $query(null), 'on' => $chosen === null],
                ...array_map(fn (array $item): array => [
                    'label' => LootRule::words($item['rule']), 'href' => $query($item['rule']), 'on' => $item['rule'] === $chosen,
                    'disabled' => $item['rule'] !== $chosen && $item['windows'] < RateSample::HOLLOW_FROM,
                ], $table['rules']),
            ],
            'tags' => $table['rows'] === [] ? [] : array_map(fn (RateFlag $flag): string => $flag->label(), array_values(array_filter($table['rows'][0]['estimate']->flags, fn (RateFlag $flag): bool => $flag !== RateFlag::QuestItem))),
            'unread' => $table['unread'] === 0 ? null : 'Left out: '.Format::count($table['unread'], 'opening').' from sessions that do not record what an object was read for.',
            'drops' => array_map(fn (array $row): array => ['cells' => [
                ['text' => (string) ($row['item'] ?? 'Item '.$row['item_id']), 'href' => '/items/'.$row['item_id'], 'tag' => in_array(RateFlag::QuestItem, $row['estimate']->flags, true) ? RateFlag::QuestItem->label() : null],
                ['rate' => $row['estimate']],
                $row['estimate']->stated() ? $row['estimate']->range() : '–',
                $row['estimate']->counts(),
                Format::number($row['per_drop'], 1),
            ]], $table['rows']),
            'map' => MapSpec::density(WorldLayer::Creatures, null, $r['map_zones'], null, dots: $r['points']),
            'hasMap' => $r['points'] !== null,
        ];
    }

    /**
     * `Opened in Riverglades at levels 41 to 42. Gives Bad Rock or Good Rock.`
     *
     * @param  array<string, mixed>  $r
     * @param  list<array{id: int, name: string|null, windows: int}>  $zones
     * @param  list<array<string, mixed>>  $rows
     */
    private static function sub(array $r, array $zones, array $rows): string
    {
        $named = array_values(array_filter(array_map(fn (array $zone): ?string => $zone['name'], array_slice($zones, 0, 2)), fn (?string $zone): bool => $zone !== null));
        $level = $r['level_min'] === null ? '' : ' at '.lcfirst(Format::range((int) $r['level_min'], (int) ($r['level_max'] ?? $r['level_min'])));
        $gives = array_map(fn (array $row): string => (string) ($row['item'] ?? 'Item '.$row['item_id']), array_slice($rows, 0, 3));
        $last = array_pop($gives);

        return 'Opened'.($named === [] ? '' : ' in '.implode(' and ', $named)).$level.'.'.($last === null ? '' : ' Gives '.($gives === [] ? '' : implode(', ', $gives).' or ').$last.'.');
    }
}
