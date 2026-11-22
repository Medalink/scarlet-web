<?php

namespace App\Actions\Rollups;

use App\Support\ItemBind;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\LootSourceKind;
use App\Support\RateEstimate;
use App\Support\RateFlag;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class SourceRates
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Where an item drops from, one row for each source and `read` (a creature looted, the same creature skinned, an
     * object, a container) over every loot rule but personal loot, or over `$rule` alone: the item's rate over the
     * windows of the source with that read, first reads only. Windows with no `read` are in no row. Sources read 10
     * times or more come first, ranked by the low end of the rate's range, then sources read fewer times, which show
     * counts only. `rules` lists the loot rules the item dropped under, for the page's rule control.
     *
     * @return array{rule: string|null, rules: list<string>, rows: list<array{source_kind: string, source_id: int, source: string|null, read: string, quantity: int, per_drop: float, estimate: RateEstimate}>}
     */
    public function handle(int $itemId, ?string $rule = null, int $limit = 200): array
    {
        $rules = $this->rules($itemId);
        $rule = in_array($rule, $rules, true) ? $rule : null;
        $quest = (int) DB::table('items')->where('item_id', $itemId)->value('bind_type') === ItemBind::Quest->value;

        $windows = LootRule::within(LootRead::known(DB::table(Rollup::LootWindowsDaily->value))
            ->selectRaw('source_kind, source_id, read, sum(windows) as windows, distinct_count(rollup(characters)) as window_characters'), 'loot_rule', $rule)
            ->groupBy('source_kind', 'source_id', 'read');
        $query = LootSourceKind::withName(LootRead::known(DB::table(Rollup::LootItemsDaily->value.' as d'), 'd.read')
            ->joinSub($windows, 'o', fn ($join) => $join->on('o.source_kind', '=', 'd.source_kind')->on('o.source_id', '=', 'd.source_id')->on('o.read', '=', 'd.read'))
            ->select('d.source_kind', 'd.source_id', 'd.read'))
            ->selectRaw('sum(d.windows) as drops, sum(d.quantity) as quantity, o.windows as opens, o.window_characters')
            ->where('d.item_id', $itemId);
        $rows = $this->read->rows(LootRule::within($query, 'd.loot_rule', $rule)
            ->groupBy('d.source_kind', 'd.source_id', 'd.read', 'o.windows', 'o.window_characters')
            ->havingRaw('distinct_count(rollup(d.characters)) >= ?', [(int) config('scarlet.min_characters')])
            ->orderByRaw('sum(d.windows) desc')->orderBy('d.source_id')->orderBy('d.read')->limit($limit));

        $rows = array_map(fn (array $row): array => [
            'source_kind' => (string) $row['source_kind'], 'source_id' => (int) $row['source_id'], 'source' => $row['source'], 'read' => (string) $row['read'],
            'quantity' => (int) $row['quantity'],
            'per_drop' => (int) $row['drops'] === 0 ? 0.0 : (int) $row['quantity'] / (int) $row['drops'],
            'estimate' => RateEstimate::of((int) $row['drops'], (int) $row['opens'], RateFlag::tags((float) $row['window_characters'], $quest)),
        ], $rows);
        usort($rows, fn (array $a, array $b): int => RateEstimate::compare($a['estimate'], $b['estimate']) ?: [$a['source_kind'], $a['source_id'], $a['read']] <=> [$b['source_kind'], $b['source_id'], $b['read']]);

        return ['rule' => $rule, 'rules' => $rules, 'rows' => $rows];
    }

    /**
     * @return list<string>
     */
    private function rules(int $itemId): array
    {
        $rows = $this->read->rows(LootRead::known(DB::table(Rollup::LootItemsDaily->value))
            ->selectRaw('loot_rule')
            ->where('item_id', $itemId)
            ->groupBy('loot_rule')
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')])
            ->orderByRaw('sum(windows) desc')->orderBy('loot_rule'));

        return array_map(fn (array $row): string => (string) $row['loot_rule'], $rows);
    }
}
