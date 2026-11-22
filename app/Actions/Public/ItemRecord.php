<?php

namespace App\Actions\Public;

use App\Actions\Rollups\ItemSources;
use App\Actions\Rollups\SourceRates;
use App\Support\NameKind;
use App\Support\Rollup;
use Illuminate\Support\Facades\DB;

class ItemRecord
{
    public function __construct(
        private readonly ItemSources $sources,
        private readonly SourceRates $sourceRates,
        private readonly ItemPrices $prices,
        private readonly ItemCrafting $crafting,
        private readonly BossDrops $bossDrops,
    ) {}

    /**
     * Everything the item page and `/public/items/{id}` show: what the item is, where it drops, by source and
     * loot rule, and its auction price history for each ruleset and realm. The drop aggregates carry no ruleset, so
     * every drop figure is over all rulesets. `by_source` is the same drops as one row for each source over every loot
     * rule, or over `$rule` alone, with each rate as its counts and range. It also lists the recipes that make it or
     * use it and the boss encounters that dropped it. Null when nothing is known by this id.
     *
     * @return array<string, mixed>|null
     */
    public function handle(int $id, ?string $rule = null): ?array
    {
        $name = DB::table('names')->where('kind', NameKind::Item->value)->where('id', $id)->orderByDesc('votes')->orderBy('name')->value('name');
        $item = DB::table('items')->where('item_id', $id)->first();
        if (! is_string($name) && $item === null) {
            return null;
        }
        $sources = $this->sources->handle($id, 50);
        $totals = DB::table(Rollup::DropsDaily->value)
            ->selectRaw('sum(drops) as dropped, min(bucket) as first_at, distinct_count(rollup(characters)) as characters')
            ->where('item_id', $id)
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')])
            ->first();

        return [
            'id' => $id,
            'name' => is_string($name) ? $name : 'Item '.$id,
            'item' => $item === null ? null : (array) $item,
            'sources' => $sources,
            'by_source' => $this->sourceRates->handle($id, $rule),
            'prices' => $this->prices->handle($id),
            ...$this->crafting->handle($id),
            'encounters' => $this->bossDrops->handle($id, null, 25),
            'dropped' => $totals === null ? null : (int) $totals->dropped,
            'first_at' => $totals?->first_at,
            'source_count' => count(array_unique(array_map(fn (array $row): string => $row['source_kind'].':'.$row['source_id'], $sources))),
            'best_rate' => $sources === [] ? null : (float) max([0.0, ...array_column($sources, 'rate')]),
        ];
    }
}
