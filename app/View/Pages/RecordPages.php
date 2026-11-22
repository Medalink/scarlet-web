<?php

namespace App\View\Pages;

use App\Support\CreatureClass;
use App\Support\KillerKind;
use App\Support\LootRule;
use App\Support\PageScope;
use App\Support\RateEstimate;
use App\Support\RateFlag;
use App\Support\RateSample;
use App\Support\WorldLayer;
use App\View\Charts;
use App\View\Format;
use App\View\ItemTypes;

/**
 * The data of the creature, item, zone and quest pages (docs/comps/creature.html, item.html, zone.html, quest.html)
 * from the answers of the matching App\Actions\Public record Actions.
 */
final class RecordPages
{
    /** An item's bind type (items.bind_type) as a sentence. */
    private const array BINDS = [1 => 'Binds when picked up.', 2 => 'Binds when equipped.', 3 => 'Binds when used.', 4 => 'Quest item.'];

    /**
     * @param  array<string, mixed>  $r  the answer of CreatureRecord
     * @return array<string, mixed>
     */
    public static function creature(array $r): array
    {
        /** @var PageScope $scope */
        $scope = $r['scope'];
        /** @var array<string, mixed> $facts */
        $facts = $r['facts'];
        /** @var array{kills: int|null, own: int|null, characters: int|null} $kills */
        $kills = $r['kills'];
        /** @var array{deaths: int|null, characters: int|null} $deaths */
        $deaths = $r['deaths'];
        /** @var array{rule: string|null, windows: int, characters: int, skins_counted: bool, rules: list<array{rule: string, windows: int}>, rows: list<array{item_id: int, item: string|null, quantity: int, per_drop: float, estimate: RateEstimate}>} $table */
        $table = $r['drop_table'];
        /** @var list<array{id: int, name: string}> $zones */
        $zones = $facts['zones'];

        $chosen = $table['rule'];
        $opens = $table['windows'] === 0 ? null : $table['windows'];
        $base = '/creatures/'.$r['id'];
        $query = fn (?string $value): string => $base.'?'.http_build_query(array_filter(['ruleset' => $scope->ruleset?->slug(), 'rule' => $value]));

        return [
            'name' => $r['name'],
            'sub' => self::creatureSub($facts, $zones),
            'filters' => Filters::onPage($scope, self::rulesets($r), $base),
            'figures' => [
                ['label' => 'Kills', 'value' => $kills['kills'] === null ? '–' : Format::number($kills['kills']), 'unit' => $kills['kills'] === null ? Format::TOO_FEW : null],
                ['label' => 'Characters killed', 'value' => $deaths['characters'] === null ? '0' : Format::number($deaths['characters'])],
                ['label' => 'Deaths per 1,000 kills', 'value' => $kills['kills'] && $deaths['deaths'] !== null ? Format::number($deaths['deaths'] / $kills['kills'] * 1000, 1) : '–'],
                ['label' => 'Corpses looted', 'value' => $opens === null ? '–' : Format::number($opens), 'unit' => $chosen === null ? null : LootRule::words($chosen)],
            ],
            'threat' => RecordThreat::creature($kills['kills'], $deaths['deaths']),
            'rules' => $table['rules'] === [] ? [] : [
                ['label' => 'All rules', 'href' => $query(null), 'on' => $chosen === null],
                ...array_map(fn (array $item): array => [
                    'label' => LootRule::words($item['rule']), 'href' => $query($item['rule']), 'on' => $item['rule'] === $chosen,
                    'disabled' => $item['rule'] !== $chosen && $item['windows'] < RateSample::HOLLOW_FROM,
                ], $table['rules']),
            ],
            'tags' => $table['rows'] === [] ? [] : array_map(fn (RateFlag $flag): string => $flag->label(), array_values(array_filter($table['rows'][0]['estimate']->flags, fn (RateFlag $flag): bool => $flag !== RateFlag::QuestItem))),
            'skins' => $table['skins_counted'],
            'drops' => array_map(fn (array $row): array => ['cells' => [
                ['text' => (string) ($row['item'] ?? 'Item '.$row['item_id']), 'href' => '/items/'.$row['item_id'], 'tag' => in_array(RateFlag::QuestItem, $row['estimate']->flags, true) ? RateFlag::QuestItem->label() : null],
                ['rate' => $row['estimate']],
                $row['estimate']->stated() ? $row['estimate']->range() : '–',
                $row['estimate']->counts(),
                Format::number($row['per_drop'], 1),
            ]], $table['rows']),
            'map' => MapSpec::density(WorldLayer::Creatures, $scope->ruleset?->slug(), $r['map_zones'], $r['points'] === null ? ($zones[0]['id'] ?? null) : null, creature: (int) $r['id'], dots: $r['points']),
            'hasMap' => $r['points'] !== null || $r['heat'] !== [],
            'dots' => $r['points'] !== null,
            'byLevel' => self::deathsByLevel($r['deaths_by_level']),
        ];
    }

    /**
     * @param  array<string, mixed>  $r  the answer of ItemRecord
     * @return array<string, mixed>
     */
    public static function item(array $r): array
    {
        /** @var array<string, mixed>|null $item */
        $item = $r['item'];
        /** @var list<array<string, mixed>> $sources */
        $sources = $r['sources'];
        $top = max(0.0001, (float) max([0.0, ...array_column($sources, 'rate')]));

        $facts = [];
        if ($item !== null) {
            $facts[] = trim(($item['item_level'] ? 'Item level '.$item['item_level'].' ' : '').ItemTypes::describe($item['class_id'], $item['subclass_id'], $item['equip_loc'])).'.';
            if ($item['required_level']) {
                $facts[] = 'Requires level '.$item['required_level'].'.';
            }
            $bind = self::BINDS[(int) $item['bind_type']] ?? null;
            if ($bind !== null) {
                $facts[] = $bind;
            }
        }
        $first = $r['first_at'] === null ? null : date('j M', (int) strtotime((string) $r['first_at']));

        return [
            'name' => $r['name'],
            'quality' => ItemTypes::quality($item['quality'] ?? null),
            'sub' => implode(' ', $facts),
            'sell' => ($item['sell_price'] ?? 0) > 0 ? Format::money($item['sell_price']) : null,
            'prices' => ItemPriceHistory::markets($r['prices']),
            'figures' => [
                ['label' => 'Dropped', 'value' => $r['dropped'] === null ? '–' : Format::number($r['dropped']), 'unit' => $r['dropped'] === null ? Format::TOO_FEW : null],
                ['label' => 'Sources', 'value' => Format::number($r['source_count'])],
                ['label' => 'Best drop rate', 'value' => $r['best_rate'] === null ? '–' : Format::percent((float) $r['best_rate'], $r['best_rate'] < 0.01 ? 2 : 1)],
                ['label' => 'First recorded', 'value' => $first ?? '–'],
            ],
            'made' => CraftingRows::recipes($r['made_by']),
            'uses' => CraftingRows::recipes($r['used_in']),
            'bosses' => CraftingRows::encounters($r['encounters']),
            'rows' => array_map(fn (array $row): array => ['cells' => [
                $row['source_kind'] === 'c' && $row['source'] !== null ? ['text' => (string) $row['source'], 'href' => '/creatures/'.$row['source_id']] : self::otherSource($row),
                LootRule::words((string) $row['loot_rule']),
                ['text' => Format::percent((float) $row['rate'], (float) $row['rate'] < 0.01 ? 2 : 1), 'bar' => max(0.15, (float) $row['rate'] / $top * 5.2).'rem'],
                Format::number($row['opens']), Format::number($row['drops']),
            ]], $sources),
        ];
    }

    /**
     * @param  array<string, mixed>  $r  the answer of ZoneRecord
     * @return array<string, mixed>
     */
    public static function zone(array $r): array
    {
        /** @var PageScope $scope */
        $scope = $r['scope'];
        /** @var array<string, mixed>|null $played */
        $played = $r['played'];
        /** @var array<string, mixed>|null $deaths */
        $deaths = $r['deaths'];
        /** @var list<array<string, mixed>> $killers */
        $killers = $r['killers'];
        /** @var list<array<string, mixed>> $creatures */
        $creatures = $r['creatures'];
        $hours = $played === null ? null : (float) $played['seconds'] / 3600;
        $deathCount = $deaths === null ? 0 : (int) $deaths['deaths'];
        $topKills = $killers === [] ? 1 : max(1, (int) $killers[0]['deaths']);
        $topSeen = $creatures === [] ? 1 : max(1, (int) $creatures[0]['sightings']);

        return [
            'name' => $r['name'],
            'facts' => array_values(array_filter([
                $played === null ? null : Format::range((int) $played['level_min'], (int) $played['level_max']),
                $hours === null ? 'No play recorded here yet' : Format::count(round($hours), 'hour').' played here',
                $deathCount === 0 ? null : Format::count($deathCount, 'death'),
            ])),
            'filters' => Filters::onPage($scope, self::rulesets($r), '/zones/'.$r['id']),
            'figures' => [
                ['label' => 'Characters', 'value' => $played === null ? '–' : Format::number($played['characters']), 'unit' => $played === null ? Format::TOO_FEW : null],
                ['label' => 'Hours', 'value' => $hours === null ? '–' : Format::number(round($hours))],
                ['label' => 'Deaths', 'value' => $deaths === null ? '0' : Format::number($deathCount)],
                ['label' => 'Deaths an hour', 'value' => $hours === null || $hours < 1 ? '–' : Format::number($deathCount / $hours, 2)],
            ],
            'threat' => RecordThreat::zone($hours, $deathCount),
            'map' => MapSpec::density(WorldLayer::Paths, $scope->ruleset?->slug(), $r['map_zones'], $r['rect'] === null ? null : (int) $r['id']),
            'floors' => self::floors($r),
            'killers' => array_map(fn (array $row): array => [
                'label' => (string) $row['killer'], 'value' => Format::number($row['deaths']), 'share' => (int) $row['deaths'] / $topKills * 100,
                'href' => $row['kind'] === KillerKind::Creature->value && $row['npc_id'] !== null ? '/creatures/'.$row['npc_id'] : null,
            ], $killers),
            'creatures' => array_map(fn (array $row): array => [
                'label' => (string) ($row['name'] ?? 'Creature '.$row['npc_id']), 'href' => '/creatures/'.$row['npc_id'],
                'value' => Format::number($row['sightings']), 'share' => (int) $row['sightings'] / $topSeen * 100,
            ], $creatures),
        ];
    }

    /**
     * A dungeon's floor maps for the page: one tab and one picture a floor, each boss a numbered pin at a share of
     * the picture and a row of the list under it. A floor with no name of its own takes the dungeon's, or its number.
     *
     * @param  array<string, mixed>  $r  the answer of ZoneRecord
     * @return list<array<string, mixed>>
     */
    private static function floors(array $r): array
    {
        /** @var list<array<string, mixed>> $floors */
        $floors = $r['floors'];

        return array_map(fn (array $floor, int $n): array => [
            'label' => $floor['name'] ?? (count($floors) === 1 ? $r['name'] : 'Floor '.($n + 1)),
            'src' => $floor['src'], 'width' => $floor['width'], 'height' => $floor['height'], 'credit' => $floor['credit'],
            'bosses' => array_map(fn (array $boss): array => [
                'label' => (string) $boss['name'], 'href' => $boss['npc_id'] === null ? null : '/creatures/'.$boss['npc_id'],
                'left' => round((float) $boss['x'] * 100, 2), 'top' => round((float) $boss['y'] * 100, 2),
            ], $floor['bosses']),
        ], $floors, array_keys($floors));
    }

    /**
     * @param  array<string, mixed>  $r  the answer of QuestRecord
     * @return array<string, mixed>
     */
    public static function quest(array $r): array
    {
        /** @var PageScope $scope */
        $scope = $r['scope'];
        /** @var array<string, mixed>|null $totals */
        $totals = $r['totals'];
        /** @var list<array{level: int, turn_ins: int}> $levels */
        $levels = $r['levels'];
        /** @var list<array{zone_id: int, zone: string|null, turn_ins: int}> $zones */
        $zones = $r['zones'];
        $turnIns = $totals === null ? null : (int) $totals['turn_ins'];
        $topZone = $zones === [] ? 1 : max(1, $zones[0]['turn_ins']);

        return [
            'name' => $r['name'],
            'sub' => $totals === null ? Format::TOO_FEW.'.' : 'Turned in '.Format::count((int) $turnIns, 'time').' by '.Format::count($totals['characters'], 'character').'.',
            'filters' => Filters::onPage($scope, self::rulesets($r), '/quests/'.$r['id']),
            'figures' => [
                ['label' => 'Turn-ins', 'value' => $turnIns === null ? '–' : Format::number($turnIns), 'unit' => $turnIns === null ? Format::TOO_FEW : null],
                ['label' => 'Accepted', 'value' => $totals === null ? '–' : Format::number($totals['accepts'])],
                ['label' => 'Abandoned', 'value' => $totals === null || (int) $totals['accepts'] === 0 ? '–' : Format::percent((int) $totals['abandons'] / (int) $totals['accepts'])],
                ['label' => 'Experience a turn-in', 'value' => $totals === null || $turnIns === 0 ? '–' : Format::number((int) $totals['xp'] / max(1, (int) $turnIns))],
            ],
            'byLevel' => self::turnInsByLevel($levels),
            'zones' => array_map(fn (array $row): array => [
                'label' => (string) ($row['zone'] ?? 'Zone '.$row['zone_id']), 'href' => '/zones/'.$row['zone_id'],
                'value' => Format::number($row['turn_ins']), 'share' => $row['turn_ins'] / $topZone * 100,
            ], $zones),
        ];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return list<array{id: int, slug: string, label: string, kind: string|null}>
     */
    private static function rulesets(array $r): array
    {
        /** @var list<array{id: int, slug: string, label: string, kind: string|null}> $rulesets */
        $rulesets = $r['rulesets'];

        return $rulesets;
    }

    /**
     * @param  array<string, mixed>  $facts
     * @param  list<array{id: int, name: string}>  $zones
     */
    private static function creatureSub(array $facts, array $zones): string
    {
        $low = $facts['level_min'];
        $high = $facts['level_max'];
        $level = Format::creatureLevel($low, $high) ?: 'Creature';
        $kind = trim(strtolower(implode(' ', array_filter([(string) ($facts['type'] ?? ''), $facts['family'] ? '('.strtolower((string) $facts['family']).')' : '']))));
        $class = $facts['classification'] && $facts['classification'] !== 'normal' ? ', '.(CreatureClass::tryFrom((string) $facts['classification'])?->label() ?? strtolower((string) $facts['classification'])) : '';

        return $level.($kind === '' ? '' : ' '.$kind).$class.'.'.($zones === [] ? '' : ' Seen in '.implode(', ', array_map(fn (array $zone): string => $zone['name'], array_slice($zones, 0, 2))).'.');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private static function deathsByLevel(array $rows): ?array
    {
        return self::levelBars($rows, 'deaths', 'death');
    }

    /**
     * @param  list<array{level: int, turn_ins: int}>  $rows
     * @return array<string, mixed>|null
     */
    private static function turnInsByLevel(array $rows): ?array
    {
        return self::levelBars($rows, 'turn_ins', 'turn-in');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private static function levelBars(array $rows, string $key, string $noun): ?array
    {
        if ($rows === []) {
            return null;
        }
        $data = $say = [];
        foreach ($rows as $row) {
            $data[] = [(int) $row['level'], (int) $row[$key]];
            $say[] = Format::line('Level '.$row['level'], ': '.Format::count($row[$key], $noun));
        }
        $axis = Axis::nice((float) max(array_column($data, 1)), 3);
        $first = $data[0][0];
        $end = $data[count($data) - 1][0];

        return Charts::bars(['w' => 480, 'h' => 250, 'x0' => $first - 1, 'x1' => $end + 1, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks']), 'xt' => Charts::ticks(Axis::spread($first, $end, 4))], $data, '', $say);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private static function otherSource(array $row): array
    {
        return ['text' => match ($row['source_kind']) {
            'g' => 'Object '.$row['source_id'],
            'i' => 'Item '.$row['source_id'],
            default => 'Source '.$row['source_id'],
        }];
    }
}
