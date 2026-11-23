<?php

namespace App\Actions\Rollups;

use App\Support\FreeForAllCounts;
use App\Support\ItemBind;
use App\Support\LootCounted;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\RateEstimate;
use App\Support\RateFlag;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SourceDrops
{
    public function __construct(private readonly RollupRead $read, private readonly FreeForAllArm $free) {}

    /**
     * What a source drops as one table: each item's rate over the windows of the source read for `$read` (first reads
     * only). A loot table is under every loot rule but personal loot, or under `$rule` alone, and `rules` lists the windows
     * of each rule the source was read under, for the page's rule control; any other read, skinning for one, does not
     * depend on the rule, so it has no rules and `$rule` is ignored. Rows rank by the low end of the rate's range. The
     * free-for-all windows of a loot table are those of the arm of the source (`FreeForAllArm`), so a table counts the
     * windows the chart and the API count; under the free-for-all rule alone its tags are the arm's.
     * `unread` counts the windows of a loot table left out because their session records no `read` (docs/LOOT.md, Metrics).
     *
     * @return array{rule: string|null, windows: int, characters: int, unread: int, rules: list<array{rule: string, windows: int}>, rows: list<array{item_id: int, item: string|null, quantity: int, per_drop: float, estimate: RateEstimate}>}
     */
    public function handle(string $sourceKind, int $sourceId, ?string $rule = null, LootRead $read = LootRead::Loot): array
    {
        $arm = $read === LootRead::Loot ? $this->free->handle($sourceKind, $sourceId) : null;
        $rules = $this->rules($sourceKind, $sourceId, $read, $arm);
        $rule = in_array($rule, array_column($rules, 'rule'), true) ? $rule : null;
        $free = $rule === null || $rule === LootRule::FreeForAll->value ? $arm : null;
        $unread = $read === LootRead::Loot ? $this->unread($sourceKind, $sourceId, $rule) : 0;
        $view = $this->windows($sourceKind, $sourceId, $rule, $read, $free);
        if ($view === null) {
            return ['rule' => $rule, 'windows' => 0, 'characters' => 0, 'unread' => $unread, 'rules' => $rules, 'rows' => []];
        }
        $armTags = $free !== null && $rule === LootRule::FreeForAll->value ? $free->arm->flags : null;
        $rows = array_map(fn (array $row): array => [
            'item_id' => (int) $row['item_id'], 'item' => $row['item'], 'quantity' => (int) $row['quantity'],
            'per_drop' => (int) $row['drops'] === 0 ? 0.0 : (int) $row['quantity'] / (int) $row['drops'],
            'estimate' => RateEstimate::of((int) $row['drops'], $view['windows'], $armTags === null
                ? RateFlag::tags($view['characters'], (int) $row['bind_type'] === ItemBind::Quest->value)
                : [...$armTags, ...((int) $row['bind_type'] === ItemBind::Quest->value ? [RateFlag::QuestItem] : [])]),
        ], $this->items($sourceKind, $sourceId, $rule, $read, $free));
        usort($rows, fn (array $a, array $b): int => RateEstimate::compare($a['estimate'], $b['estimate']) ?: $a['item_id'] <=> $b['item_id']);

        return ['rule' => $rule, 'windows' => $view['windows'], 'characters' => $view['characters'], 'unread' => $unread, 'rules' => $rules, 'rows' => $rows];
    }

    /**
     * @return list<array{rule: string, windows: int}>
     */
    private function rules(string $sourceKind, int $sourceId, LootRead $read, ?FreeForAllCounts $free): array
    {
        if ($read !== LootRead::Loot) {
            return [];
        }
        $rows = $this->read->rows($read->within(DB::table(LootCounted::Windows->value))
            ->selectRaw('loot_rule, sum(windows) as windows')
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId)
            ->groupBy('loot_rule')
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')])
            ->orderByRaw('sum(windows) desc')->orderBy('loot_rule'));
        $rules = array_map(fn (array $row): array => [
            'rule' => (string) $row['loot_rule'],
            'windows' => $free !== null && $row['loot_rule'] === LootRule::FreeForAll->value ? $free->arm->windows : (int) $row['windows'],
        ], $rows);
        if ($free !== null) {
            usort($rules, fn (array $a, array $b): int => [$b['windows'], $a['rule']] <=> [$a['windows'], $b['rule']]);
        }

        return $rules;
    }

    /**
     * @return array{windows: int, characters: int}|null
     */
    private function windows(string $sourceKind, int $sourceId, ?string $rule, LootRead $read, ?FreeForAllCounts $free): ?array
    {
        $query = $read->within(DB::table(LootCounted::Windows->value))
            ->selectRaw("sum(windows) as windows, sum(windows) filter (where loot_rule = '".LootRule::FreeForAll->value."') as free_windows, distinct_count(rollup(characters)) as characters")
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId);
        $row = $this->read->rows($this->ruled($query, 'loot_rule', $rule, $read)
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')]))[0] ?? null;
        if ($row === null || $row['windows'] === null) {
            return null;
        }
        $windows = (int) $row['windows'] - ($free === null ? 0 : (int) $row['free_windows'] - $free->arm->windows);
        $characters = $free !== null && $rule === LootRule::FreeForAll->value ? count($free->arm->characters) : (int) round((float) $row['characters']);

        return ['windows' => $windows, 'characters' => $characters];
    }

    private function unread(string $sourceKind, int $sourceId, ?string $rule): int
    {
        $query = DB::table(LootCounted::Windows->value)
            ->selectRaw('sum(windows) as windows')
            ->whereNull('read')->where('source_kind', $sourceKind)->where('source_id', $sourceId);
        $row = $this->read->rows(LootRule::within($query, 'loot_rule', $rule)
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')]))[0] ?? null;

        return $row === null ? 0 : (int) $row['windows'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(string $sourceKind, int $sourceId, ?string $rule, LootRead $read, ?FreeForAllCounts $free): array
    {
        $query = $read->within(DB::table(LootCounted::Items->value.' as d'), 'd.read')
            ->leftJoin('items as i', 'i.item_id', '=', 'd.item_id')
            ->selectRaw("d.item_id, (select n.name from names n where n.kind = 'item' and n.id = d.item_id order by n.votes desc, n.name limit 1) as item")
            ->selectRaw("sum(d.windows) as drops, sum(d.quantity) as quantity, i.bind_type, sum(d.windows) filter (where d.loot_rule = '".LootRule::FreeForAll->value."') as free_drops, sum(d.quantity) filter (where d.loot_rule = '".LootRule::FreeForAll->value."') as free_quantity")
            ->where('d.source_kind', $sourceKind)->where('d.source_id', $sourceId);

        $rows = $this->read->rows($this->ruled($query, 'd.loot_rule', $rule, $read)
            ->groupBy('d.item_id', 'i.bind_type')
            ->havingRaw('distinct_count(rollup(d.characters)) >= ?', [(int) config('scarlet.min_characters')]));
        if ($free === null) {
            return $rows;
        }
        $counted = [];
        foreach ($rows as $row) {
            $armed = $free->items[(int) $row['item_id']] ?? ['drops' => 0, 'quantity' => 0];
            $row['drops'] = (int) $row['drops'] - (int) $row['free_drops'] + $armed['drops'];
            $row['quantity'] = (int) $row['quantity'] - (int) $row['free_quantity'] + $armed['quantity'];
            if ($row['drops'] > 0) {
                $counted[] = $row;
            }
        }

        return $counted;
    }

    /** Skinning and the other gathering reads do not depend on the loot rule, so only a loot table is narrowed by it. */
    private function ruled(Builder $query, string $column, ?string $rule, LootRead $read): Builder
    {
        return $read === LootRead::Loot ? LootRule::within($query, $column, $rule) : $query;
    }
}
