<?php

namespace App\Actions\Rollups;

use App\Support\ItemBind;
use App\Support\ItemClass;
use App\Support\LootRule;
use App\Support\RateEstimate;
use App\Support\RateFlag;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class SourceRates
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Where an item drops from, one row for each source over every loot rule but personal loot, or over `$rule`
     * alone: the item's rate over the corpses of the source looted for the first time. Sources looted 10 times or more
     * come first, ranked by the low end of the rate's range, then sources looted fewer times, which show counts only.
     * `rules` lists the loot rules the item dropped under, for the page's rule control.
     *
     * @return array{rule: string|null, rules: list<string>, rows: list<array{source_kind: string, source_id: int, source: string|null, quantity: int, per_drop: float, estimate: RateEstimate}>}
     */
    public function handle(int $itemId, ?string $rule = null, int $limit = 200): array
    {
        $rules = $this->rules($itemId);
        $rule = in_array($rule, $rules, true) ? $rule : null;
        $quest = (int) DB::table('items')->where('item_id', $itemId)->value('bind_type') === ItemBind::Quest->value;

        $opens = LootRule::within(DB::table(Rollup::OpensDaily->value)
            ->selectRaw('source_kind, source_id, sum(opens) as opens, distinct_count(rollup(characters)) as window_characters'), 'loot_rule', $rule)
            ->groupBy('source_kind', 'source_id');
        $query = DB::table(Rollup::DropsDaily->value.' as d')
            ->joinSub($opens, 'o', fn ($join) => $join->on('o.source_kind', '=', 'd.source_kind')->on('o.source_id', '=', 'd.source_id'))
            ->selectRaw("d.source_kind, d.source_id, (select n.name from names n where n.kind = 'npc' and d.source_kind = 'c' and n.id = d.source_id order by n.votes desc, n.name limit 1) as source")
            ->selectRaw('sum(d.drops) as drops, sum(d.quantity) as quantity, o.opens, o.window_characters')
            ->selectRaw('exists (select 1 from drops_daily l join items li on li.item_id = l.item_id where l.source_kind = d.source_kind and l.source_id = d.source_id and li.class_id = ? and li.subclass_id = ?) as skins', [ItemClass::TradeGoods->value, ItemClass::LEATHER])
            ->where('d.item_id', $itemId);
        $rows = $this->read->rows(LootRule::within($query, 'd.loot_rule', $rule)
            ->groupBy('d.source_kind', 'd.source_id', 'o.opens', 'o.window_characters')
            ->havingRaw('distinct_count(rollup(d.characters)) >= ?', [(int) config('scarlet.min_characters')])
            ->orderByRaw('sum(d.drops) desc')->orderBy('d.source_id')->limit($limit));

        $rows = array_map(fn (array $row): array => [
            'source_kind' => (string) $row['source_kind'], 'source_id' => (int) $row['source_id'], 'source' => $row['source'],
            'quantity' => (int) $row['quantity'],
            'per_drop' => (int) $row['drops'] === 0 ? 0.0 : (int) $row['quantity'] / (int) $row['drops'],
            'estimate' => RateEstimate::of((int) $row['drops'], (int) $row['opens'], array_values(array_filter([
                (int) round((float) $row['window_characters']) === 1 ? RateFlag::OneCharacter : null,
                $row['skins'] ? RateFlag::SkinsCounted : null,
                $quest ? RateFlag::QuestItem : null,
            ]))),
        ], $rows);
        usort($rows, fn (array $a, array $b): int => RateEstimate::compare($a['estimate'], $b['estimate']) ?: [$a['source_kind'], $a['source_id']] <=> [$b['source_kind'], $b['source_id']]);

        return ['rule' => $rule, 'rules' => $rules, 'rows' => $rows];
    }

    /**
     * @return list<string>
     */
    private function rules(int $itemId): array
    {
        $rows = $this->read->rows(DB::table(Rollup::DropsDaily->value)
            ->selectRaw('loot_rule')
            ->where('item_id', $itemId)
            ->groupBy('loot_rule')
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')])
            ->orderByRaw('sum(drops) desc')->orderBy('loot_rule'));

        return array_map(fn (array $row): string => (string) $row['loot_rule'], $rows);
    }
}
