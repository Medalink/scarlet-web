<?php

namespace App\Actions\Rollups;

use App\Support\ItemBind;
use App\Support\ItemClass;
use App\Support\LootRule;
use App\Support\RateEstimate;
use App\Support\RateFlag;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SourceDrops
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * What a source drops as one table: each item's rate over the corpses of the source looted for the first time,
     * under every loot rule but personal loot, or under `$rule` alone. `rules` lists the windows of each rule the
     * source was looted under, for the page's rule control. Rows rank by the low end of the rate's range.
     *
     * @return array{rule: string|null, windows: int, characters: int, skins_counted: bool, rules: list<array{rule: string, windows: int}>, rows: list<array{item_id: int, item: string|null, quantity: int, per_drop: float, estimate: RateEstimate}>}
     */
    public function handle(string $sourceKind, int $sourceId, ?string $rule = null): array
    {
        $rules = $this->rules($sourceKind, $sourceId);
        $rule = in_array($rule, array_column($rules, 'rule'), true) ? $rule : null;
        $view = $this->windows($sourceKind, $sourceId, $rule);
        if ($view === null) {
            return ['rule' => $rule, 'windows' => 0, 'characters' => 0, 'skins_counted' => false, 'rules' => $rules, 'rows' => []];
        }
        $items = $this->items($sourceKind, $sourceId, $rule);
        $skins = array_filter($items, fn (array $row): bool => (int) $row['class_id'] === ItemClass::TradeGoods->value && (int) $row['subclass_id'] === ItemClass::LEATHER) !== [];
        $rows = array_map(fn (array $row): array => [
            'item_id' => (int) $row['item_id'], 'item' => $row['item'], 'quantity' => (int) $row['quantity'],
            'per_drop' => (int) $row['drops'] === 0 ? 0.0 : (int) $row['quantity'] / (int) $row['drops'],
            'estimate' => RateEstimate::of((int) $row['drops'], $view['windows'], array_values(array_filter([
                $view['characters'] === 1 ? RateFlag::OneCharacter : null,
                $skins ? RateFlag::SkinsCounted : null,
                (int) $row['bind_type'] === ItemBind::Quest->value ? RateFlag::QuestItem : null,
            ]))),
        ], $items);
        usort($rows, fn (array $a, array $b): int => RateEstimate::compare($a['estimate'], $b['estimate']) ?: $a['item_id'] <=> $b['item_id']);

        return ['rule' => $rule, 'windows' => $view['windows'], 'characters' => $view['characters'], 'skins_counted' => $skins, 'rules' => $rules, 'rows' => $rows];
    }

    /**
     * @return list<array{rule: string, windows: int}>
     */
    private function rules(string $sourceKind, int $sourceId): array
    {
        $rows = $this->read->rows(DB::table(Rollup::OpensDaily->value)
            ->selectRaw('loot_rule, sum(opens) as opens')
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId)
            ->groupBy('loot_rule')
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')])
            ->orderByRaw('sum(opens) desc')->orderBy('loot_rule'));

        return array_map(fn (array $row): array => ['rule' => (string) $row['loot_rule'], 'windows' => (int) $row['opens']], $rows);
    }

    /**
     * @return array{windows: int, characters: int}|null
     */
    private function windows(string $sourceKind, int $sourceId, ?string $rule): ?array
    {
        $query = DB::table(Rollup::OpensDaily->value)
            ->selectRaw('sum(opens) as opens, distinct_count(rollup(characters)) as characters')
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId);
        $row = $this->read->rows($this->inView($query, 'loot_rule', $rule)
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')]))[0] ?? null;

        return $row === null || $row['opens'] === null ? null : ['windows' => (int) $row['opens'], 'characters' => (int) round((float) $row['characters'])];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(string $sourceKind, int $sourceId, ?string $rule): array
    {
        $query = DB::table(Rollup::DropsDaily->value.' as d')
            ->leftJoin('items as i', 'i.item_id', '=', 'd.item_id')
            ->selectRaw("d.item_id, (select n.name from names n where n.kind = 'item' and n.id = d.item_id order by n.votes desc, n.name limit 1) as item")
            ->selectRaw('sum(d.drops) as drops, sum(d.quantity) as quantity, i.class_id, i.subclass_id, i.bind_type')
            ->where('d.source_kind', $sourceKind)->where('d.source_id', $sourceId);

        return $this->read->rows($this->inView($query, 'd.loot_rule', $rule)
            ->groupBy('d.item_id', 'i.class_id', 'i.subclass_id', 'i.bind_type')
            ->havingRaw('distinct_count(rollup(d.characters)) >= ?', [(int) config('scarlet.min_characters')]));
    }

    /** One rule, or every rule but personal loot, whose windows each read a table of their own. */
    private function inView(Builder $query, string $column, ?string $rule): Builder
    {
        return $rule === null ? $query->where($column, '<>', LootRule::Personal->value) : $query->where($column, $rule);
    }
}
