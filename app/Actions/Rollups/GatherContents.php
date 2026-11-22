<?php

namespace App\Actions\Rollups;

use App\Support\GatherKind;
use App\Support\NameKind;
use App\Support\Rollup;
use App\Support\RollupRead;

class GatherContents
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * What came out of caught containers once they were opened, most opens first. `opens` counts the item rows. An item
     * fewer than `config('scarlet.min_characters')` characters opened is left out.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array{item_id: int, name: string|null, quantity: int, opens: int}>
     */
    public function handle(GatherKind $kind, int|array|null $rulesetId = null, int $limit = 8): array
    {
        $query = $this->read->from(Rollup::GatherItemsDailyRuleset, $rulesetId)
            ->where('kind', $kind->value)
            ->whereNotNull('container_item_id')
            ->selectRaw('item_id, (select n.name from names n where n.kind = ? and n.id = item_id order by n.votes desc, n.name limit 1) as name, sum(quantity) as quantity, sum(items) as opens', [NameKind::Item->value])
            ->groupBy('item_id')
            ->orderByRaw('sum(items) desc')
            ->orderBy('item_id')
            ->limit($limit);

        return array_map(fn (array $row): array => [
            'item_id' => (int) $row['item_id'],
            'name' => $row['name'] === null ? null : (string) $row['name'],
            'quantity' => (int) $row['quantity'],
            'opens' => (int) $row['opens'],
        ], $this->read->rows($this->read->shown($query)));
    }
}
