<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\GatherKind;
use App\Support\NameKind;
use App\Support\Rollup;
use App\Support\RollupRead;

class GatherItems
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * What the attempts gave, most gathers first, not what came out of a caught container. `gathers` counts the item
     * rows, `quantity` what they held, `median_skill` is the median rank of the character at the time. `zone` is where
     * the item came up most, null when no zone with enough characters has it. An item fewer than
     * the scope's threshold of characters got is left out.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return list<array{item_id: int, name: string|null, quantity: int, gathers: int, characters: int|null, median_skill: float|null, zone: array{zone_id: int, name: string|null}|null}>
     */
    public function handle(GatherKind $kind, int|array|CharacterScope|null $scope = null, int $limit = 12): array
    {
        $query = $this->read->from(Rollup::GatherItemsDailyRuleset, $scope)
            ->where('kind', $kind->value)
            ->whereNull('container_item_id')
            ->selectRaw('item_id, (select n.name from names n where n.kind = ? and n.id = item_id order by n.votes desc, n.name limit 1) as name, sum(quantity) as quantity, sum(items) as gathers, distinct_count(rollup(characters)) as characters, approx_percentile(0.5, rollup(skill_digest)) as median_skill', [NameKind::Item->value])
            ->groupBy('item_id')
            ->orderByRaw('sum(items) desc')
            ->orderBy('item_id')
            ->limit($limit);
        $rows = $this->read->rows($this->read->shown($query, $scope));
        $zones = $this->zones($kind, $scope, array_map(fn (array $row): int => (int) $row['item_id'], $rows));

        return array_map(fn (array $row): array => [
            'item_id' => (int) $row['item_id'],
            'name' => $row['name'] === null ? null : (string) $row['name'],
            'quantity' => (int) $row['quantity'],
            'gathers' => (int) $row['gathers'],
            'characters' => (int) $row['characters'],
            'median_skill' => $row['median_skill'] === null ? null : (float) $row['median_skill'],
            'zone' => $zones[(int) $row['item_id']] ?? null,
        ], $rows);
    }

    /**
     * @param  int|list<int>|CharacterScope|null  $scope
     * @param  list<int>  $items
     * @return array<int, array{zone_id: int, name: string|null}> item id => the zone it came up most in
     */
    private function zones(GatherKind $kind, int|array|CharacterScope|null $scope, array $items): array
    {
        if ($items === []) {
            return [];
        }
        $query = $this->read->from(Rollup::GatherItemsDailyRuleset, $scope)
            ->where('kind', $kind->value)
            ->whereNull('container_item_id')
            ->whereIn('item_id', $items)
            ->whereNotNull('zone_id')
            ->selectRaw('item_id, zone_id, (select z.name from zones z where z.id = zone_id) as name')
            ->groupBy('item_id', 'zone_id')
            ->orderBy('item_id')
            ->orderByRaw('sum(items) desc')
            ->orderBy('zone_id');

        $zones = [];
        foreach ($this->read->rows($this->read->shown($query, $scope)) as $row) {
            $zones[(int) $row['item_id']] ??= ['zone_id' => (int) $row['zone_id'], 'name' => $row['name'] === null ? null : (string) $row['name']];
        }

        return $zones;
    }
}
