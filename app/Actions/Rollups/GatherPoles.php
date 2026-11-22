<?php

namespace App\Actions\Rollups;

use App\Support\GatherKind;
use App\Support\NameKind;
use App\Support\Rollup;
use App\Support\RollupRead;

class GatherPoles
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The casts by the pole in the main hand, most first. A pole fewer than `config('scarlet.min_characters')`
     * characters cast with is left out.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array{item_id: int, name: string|null, casts: int}>
     */
    public function handle(GatherKind $kind, int|array|null $rulesetId = null, int $limit = 6): array
    {
        $query = $this->read->from(Rollup::GathersDailyGearRuleset, $rulesetId)
            ->where('kind', $kind->value)
            ->whereNotNull('pole_item')
            ->selectRaw('pole_item as item_id, (select n.name from names n where n.kind = ? and n.id = pole_item order by n.votes desc, n.name limit 1) as name, sum(casts) as casts', [NameKind::Item->value])
            ->groupBy('pole_item')
            ->orderByRaw('sum(casts) desc')
            ->orderBy('pole_item')
            ->limit($limit);

        return array_map(fn (array $row): array => [
            'item_id' => (int) $row['item_id'],
            'name' => $row['name'] === null ? null : (string) $row['name'],
            'casts' => (int) $row['casts'],
        ], $this->read->rows($this->read->shown($query)));
    }
}
