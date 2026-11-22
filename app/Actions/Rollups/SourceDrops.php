<?php

namespace App\Actions\Rollups;

use App\Support\ItemBind;
use App\Support\LootRead;
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
     * What a source drops as one table: each item's rate over the windows of the source read for `$read` (first reads
     * only). A loot table is under every loot rule but personal loot, or under `$rule` alone, and `rules` lists the windows
     * of each rule the source was read under, for the page's rule control; any other read, skinning for one, does not
     * depend on the rule, so it has no rules and `$rule` is ignored. Rows rank by the low end of the rate's range.
     * `unread` counts the windows of a loot table left out because their session records no `read` (docs/LOOT.md, Metrics).
     *
     * @return array{rule: string|null, windows: int, characters: int, unread: int, rules: list<array{rule: string, windows: int}>, rows: list<array{item_id: int, item: string|null, quantity: int, per_drop: float, estimate: RateEstimate}>}
     */
    public function handle(string $sourceKind, int $sourceId, ?string $rule = null, LootRead $read = LootRead::Loot): array
    {
        $rules = $this->rules($sourceKind, $sourceId, $read);
        $rule = in_array($rule, array_column($rules, 'rule'), true) ? $rule : null;
        $unread = $read === LootRead::Loot ? $this->unread($sourceKind, $sourceId, $rule) : 0;
        $view = $this->windows($sourceKind, $sourceId, $rule, $read);
        if ($view === null) {
            return ['rule' => $rule, 'windows' => 0, 'characters' => 0, 'unread' => $unread, 'rules' => $rules, 'rows' => []];
        }
        $rows = array_map(fn (array $row): array => [
            'item_id' => (int) $row['item_id'], 'item' => $row['item'], 'quantity' => (int) $row['quantity'],
            'per_drop' => (int) $row['drops'] === 0 ? 0.0 : (int) $row['quantity'] / (int) $row['drops'],
            'estimate' => RateEstimate::of((int) $row['drops'], $view['windows'], RateFlag::tags($view['characters'], (int) $row['bind_type'] === ItemBind::Quest->value)),
        ], $this->items($sourceKind, $sourceId, $rule, $read));
        usort($rows, fn (array $a, array $b): int => RateEstimate::compare($a['estimate'], $b['estimate']) ?: $a['item_id'] <=> $b['item_id']);

        return ['rule' => $rule, 'windows' => $view['windows'], 'characters' => $view['characters'], 'unread' => $unread, 'rules' => $rules, 'rows' => $rows];
    }

    /**
     * @return list<array{rule: string, windows: int}>
     */
    private function rules(string $sourceKind, int $sourceId, LootRead $read): array
    {
        if ($read !== LootRead::Loot) {
            return [];
        }
        $rows = $this->read->rows($read->within(DB::table(Rollup::LootWindowsDaily->value))
            ->selectRaw('loot_rule, sum(windows) as windows')
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId)
            ->groupBy('loot_rule')
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')])
            ->orderByRaw('sum(windows) desc')->orderBy('loot_rule'));

        return array_map(fn (array $row): array => ['rule' => (string) $row['loot_rule'], 'windows' => (int) $row['windows']], $rows);
    }

    /**
     * @return array{windows: int, characters: int}|null
     */
    private function windows(string $sourceKind, int $sourceId, ?string $rule, LootRead $read): ?array
    {
        $query = $read->within(DB::table(Rollup::LootWindowsDaily->value))
            ->selectRaw('sum(windows) as windows, distinct_count(rollup(characters)) as characters')
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId);
        $row = $this->read->rows($this->ruled($query, 'loot_rule', $rule, $read)
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')]))[0] ?? null;

        return $row === null || $row['windows'] === null ? null : ['windows' => (int) $row['windows'], 'characters' => (int) round((float) $row['characters'])];
    }

    private function unread(string $sourceKind, int $sourceId, ?string $rule): int
    {
        $query = DB::table(Rollup::LootWindowsDaily->value)
            ->selectRaw('sum(windows) as windows')
            ->whereNull('read')->where('source_kind', $sourceKind)->where('source_id', $sourceId);
        $row = $this->read->rows(LootRule::within($query, 'loot_rule', $rule)
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')]))[0] ?? null;

        return $row === null ? 0 : (int) $row['windows'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(string $sourceKind, int $sourceId, ?string $rule, LootRead $read): array
    {
        $query = $read->within(DB::table(Rollup::LootItemsDaily->value.' as d'), 'd.read')
            ->leftJoin('items as i', 'i.item_id', '=', 'd.item_id')
            ->selectRaw("d.item_id, (select n.name from names n where n.kind = 'item' and n.id = d.item_id order by n.votes desc, n.name limit 1) as item")
            ->selectRaw('sum(d.windows) as drops, sum(d.quantity) as quantity, i.bind_type')
            ->where('d.source_kind', $sourceKind)->where('d.source_id', $sourceId);

        return $this->read->rows($this->ruled($query, 'd.loot_rule', $rule, $read)
            ->groupBy('d.item_id', 'i.bind_type')
            ->havingRaw('distinct_count(rollup(d.characters)) >= ?', [(int) config('scarlet.min_characters')]));
    }

    /** Skinning and the other gathering reads do not depend on the loot rule, so only a loot table is narrowed by it. */
    private function ruled(Builder $query, string $column, ?string $rule, LootRead $read): Builder
    {
        return $read === LootRead::Loot ? LootRule::within($query, $column, $rule) : $query;
    }
}
