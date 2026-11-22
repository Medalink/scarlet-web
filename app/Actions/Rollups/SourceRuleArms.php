<?php

namespace App\Actions\Rollups;

use App\Support\ArmSplit;
use App\Support\ItemBind;
use App\Support\LootArm;
use App\Support\LootCounted;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\RateDifference;
use App\Support\RateEstimate;
use App\Support\RateFlag;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class SourceRuleArms
{
    /** An item is a row of a chart from this many drops over the arms in scope. */
    private const int CHART_FROM_DROPS = 10;

    public function __construct(private readonly RollupRead $read) {}

    /**
     * One source's drop table split into arms, by loot rule or by group size inside one rule (docs/LOOT.md, Creature
     * drops): a row for each item with 10 drops or more, and the rate in each arm that has 10 windows or more, over
     * windows read for loot, each corpse with a key once (docs/LOOT.md, Units of counting, rule 7). The windows with no key of a free-for-all arm of two or more characters count their one reader (`LootArm`). The two
     * drawn arms with the most windows, never personal loot, are compared, and each row carries their difference over
     * the items of the chart. `rules` and `sizes` list what was recorded, for the empty states; `rule` is the rule a
     * size split reads (`$rule` when the source has windows under it, else the rule with the most windows).
     *
     * @return array{split: ArmSplit, rule: string|null, rules: list<array{rule: string, windows: int}>, sizes: list<array{size: int, windows: int}>, arms: list<LootArm>, pair: list<LootArm>, rows: list<array{item_id: int, item: string|null, drops: int, cells: list<array{arm: LootArm, estimate: RateEstimate}>, difference: RateDifference|null}>}
     */
    public function handle(string $sourceKind, int $sourceId, ArmSplit $split, ?string $rule = null): array
    {
        $windows = $this->windows($sourceKind, $sourceId);
        $rules = $this->rulesSeen($windows);
        $chosen = $split === ArmSplit::Size ? $this->sizeRule($rules, $rule) : null;
        $arms = $this->arms($split, $windows, $chosen);
        $pair = $this->pair($arms);
        $sizes = $chosen === null ? [] : $this->sizesSeen($windows, $chosen);
        $answer = ['split' => $split, 'rule' => $chosen, 'rules' => $rules, 'sizes' => $sizes, 'arms' => $arms, 'pair' => $pair, 'rows' => []];
        $drawn = array_values(array_filter($arms, fn (LootArm $arm): bool => $arm->drawn()));
        if (count($drawn) < 2) {
            return $answer;
        }

        $items = $this->items($sourceKind, $sourceId);
        $readers = array_filter($drawn, fn (LootArm $arm): bool => $arm->reader !== null) === [] ? [] : $this->readerItems($sourceKind, $sourceId);
        $listed = $this->listed($items, $split, $chosen);
        $answer['rows'] = array_map(fn (array $item): array => $this->row($item, $drawn, $pair, count($listed), $items, $readers, $split, $chosen), $listed);

        return $answer;
    }

    /**
     * @return list<array{loot_rule: string, size: int|null, character: int, keyed: bool, windows: int}>
     */
    private function windows(string $sourceKind, int $sourceId): array
    {
        $rows = $this->read->rows(LootRead::Loot->within(DB::table(LootCounted::WindowsCharacter->value))
            ->selectRaw('loot_rule, group_size, character_id, keyed::int as keyed, sum(windows) as windows')
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId)
            ->groupBy('loot_rule', 'group_size', 'character_id', 'keyed'));

        return array_map(fn (array $row): array => [
            'loot_rule' => (string) $row['loot_rule'], 'size' => $row['group_size'] === null ? null : max(1, (int) $row['group_size']),
            'character' => (int) $row['character_id'], 'keyed' => (int) $row['keyed'] === 1, 'windows' => (int) $row['windows'],
        ], $rows);
    }

    /**
     * @param  list<array{loot_rule: string, size: int|null, character: int, keyed: bool, windows: int}>  $windows
     * @return list<array{rule: string, windows: int}>
     */
    private function rulesSeen(array $windows): array
    {
        $totals = [];
        foreach ($windows as $row) {
            $totals[$row['loot_rule']] = ($totals[$row['loot_rule']] ?? 0) + $row['windows'];
        }
        arsort($totals);

        return array_map(fn (string $rule, int $count): array => ['rule' => $rule, 'windows' => $count], array_keys($totals), array_values($totals));
    }

    /**
     * @param  list<array{rule: string, windows: int}>  $rules
     */
    private function sizeRule(array $rules, ?string $rule): ?string
    {
        if (in_array($rule, array_column($rules, 'rule'), true)) {
            return $rule;
        }
        $others = array_values(array_filter($rules, fn (array $seen): bool => $seen['rule'] !== LootRule::Personal->value));

        return ($others[0] ?? $rules[0] ?? null)['rule'] ?? null;
    }

    /**
     * @param  list<array{loot_rule: string, size: int|null, character: int, keyed: bool, windows: int}>  $windows
     * @return list<array{size: int, windows: int}>
     */
    private function sizesSeen(array $windows, string $rule): array
    {
        $totals = [];
        foreach ($windows as $row) {
            if ($row['loot_rule'] === $rule && $row['size'] !== null) {
                $totals[$row['size']] = ($totals[$row['size']] ?? 0) + $row['windows'];
            }
        }
        ksort($totals);

        return array_map(fn (int $size, int $count): array => ['size' => $size, 'windows' => $count], array_keys($totals), array_values($totals));
    }

    /**
     * @param  list<array{loot_rule: string, size: int|null, character: int, keyed: bool, windows: int}>  $windows
     * @return list<LootArm>
     */
    private function arms(ArmSplit $split, array $windows, ?string $chosen): array
    {
        $groups = [];
        foreach ($windows as $row) {
            if ($split === ArmSplit::Size && ($row['loot_rule'] !== $chosen || $row['size'] === null)) {
                continue;
            }
            $key = $split === ArmSplit::Rule ? $row['loot_rule'] : (string) $row['size'];
            $part = $row['keyed'] ? 'keyed' : 'unkeyed';
            $groups[$key][$part][$row['character']] = ($groups[$key][$part][$row['character']] ?? 0) + $row['windows'];
        }
        $arms = [];
        foreach ($groups as $key => $parts) {
            $key = (string) $key;
            $rule = $split === ArmSplit::Rule ? $key : (string) $chosen;
            $arm = LootArm::of(
                $key, $split === ArmSplit::Rule ? LootRule::words($key) : 'Size '.$key, $parts['unkeyed'] ?? [], $parts['keyed'] ?? [],
                $rule === LootRule::FreeForAll->value, $split === ArmSplit::Rule && $rule === LootRule::Personal->value, $rule === LootRule::Unknown->value,
                (int) config('scarlet.min_characters'),
            );
            if ($arm !== null) {
                $arms[] = $arm;
            }
        }
        usort($arms, fn (LootArm $a, LootArm $b): int => $split === ArmSplit::Size ? (int) $a->key <=> (int) $b->key : [$b->windows, $a->key] <=> [$a->windows, $b->key]);

        return $arms;
    }

    /**
     * The two drawn arms with the most windows that are not personal loot, in the order they are drawn.
     *
     * @param  list<LootArm>  $arms
     * @return list<LootArm>
     */
    private function pair(array $arms): array
    {
        $candidates = array_values(array_filter($arms, fn (LootArm $arm): bool => $arm->drawn() && ! $arm->personal));
        $ranked = $candidates;
        usort($ranked, fn (LootArm $a, LootArm $b): int => $b->windows <=> $a->windows);
        $top = array_map(fn (LootArm $arm): string => $arm->key, array_slice($ranked, 0, 2));

        return count($top) < 2 ? [] : array_values(array_filter($candidates, fn (LootArm $arm): bool => in_array($arm->key, $top, true)));
    }

    /**
     * @return list<array<string, mixed>> item_id, item, bind_type, loot_rule, group_size, keyed, drops
     */
    private function items(string $sourceKind, int $sourceId): array
    {
        return $this->read->rows(LootRead::Loot->within(DB::table(LootCounted::Items->value.' as d'), 'd.read')
            ->leftJoin('items as i', 'i.item_id', '=', 'd.item_id')
            ->selectRaw("d.item_id, (select n.name from names n where n.kind = 'item' and n.id = d.item_id order by n.votes desc, n.name limit 1) as item")
            ->selectRaw('i.bind_type, d.loot_rule, d.group_size, d.keyed::int as keyed, sum(d.windows) as drops')
            ->where('d.source_kind', $sourceKind)->where('d.source_id', $sourceId)
            ->groupBy('d.item_id', 'i.bind_type', 'd.loot_rule', 'd.group_size', 'd.keyed'));
    }

    /**
     * @return array<int, array<int, array<int, int>>> windows with no corpse key that listed the item, by item, size and character, free for all
     */
    private function readerItems(string $sourceKind, int $sourceId): array
    {
        $rows = $this->read->rows(LootRead::Loot->within(DB::table(Rollup::LootFreeForAllItemsUnkeyedDaily->value))
            ->selectRaw('item_id, group_size, character_id, sum(windows) as windows')
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId)
            ->groupBy('item_id', 'group_size', 'character_id'));
        $byItem = [];
        foreach ($rows as $row) {
            $size = $row['group_size'] === null ? 0 : max(1, (int) $row['group_size']);
            $byItem[(int) $row['item_id']][$size][(int) $row['character_id']] = ($byItem[(int) $row['item_id']][$size][(int) $row['character_id']] ?? 0) + (int) $row['windows'];
        }

        return $byItem;
    }

    /**
     * The items that are rows of the chart: 10 drops or more over the arms in scope, most dropped first.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array{item_id: int, item: string|null, bind_type: int, drops: int}>
     */
    private function listed(array $items, ArmSplit $split, ?string $chosen): array
    {
        $drops = $names = $binds = [];
        foreach ($items as $row) {
            $inScope = $split === ArmSplit::Size ? $row['loot_rule'] === $chosen : $row['loot_rule'] !== LootRule::Personal->value;
            if (! $inScope) {
                continue;
            }
            $id = (int) $row['item_id'];
            $drops[$id] = ($drops[$id] ?? 0) + (int) $row['drops'];
            $names[$id] = $row['item'];
            $binds[$id] = (int) $row['bind_type'];
        }
        $listed = array_keys(array_filter($drops, fn (int $count): bool => $count >= self::CHART_FROM_DROPS));
        usort($listed, fn (int $a, int $b): int => [$drops[$b], $a] <=> [$drops[$a], $b]);

        return array_map(fn (int $id): array => ['item_id' => $id, 'item' => $names[$id], 'bind_type' => $binds[$id], 'drops' => $drops[$id]], $listed);
    }

    /**
     * @param  array{item_id: int, item: string|null, bind_type: int, drops: int}  $item
     * @param  list<LootArm>  $drawn
     * @param  list<LootArm>  $pair
     * @param  list<array<string, mixed>>  $items
     * @param  array<int, array<int, array<int, int>>>  $readers
     * @return array{item_id: int, item: string|null, drops: int, cells: list<array{arm: LootArm, estimate: RateEstimate}>, difference: RateDifference|null}
     */
    private function row(array $item, array $drawn, array $pair, int $compared, array $items, array $readers, ArmSplit $split, ?string $chosen): array
    {
        $quest = $item['bind_type'] === ItemBind::Quest->value ? [RateFlag::QuestItem] : [];
        $cells = array_map(fn (LootArm $arm): array => [
            'arm' => $arm,
            'estimate' => RateEstimate::of($this->listing($item['item_id'], $arm, $items, $readers, $split, $chosen), $arm->windows, [...$arm->flags, ...$quest]),
        ], $drawn);
        $estimates = [];
        foreach ($cells as $cell) {
            $estimates[$cell['arm']->key] = $cell['estimate'];
        }

        return [
            'item_id' => $item['item_id'], 'item' => $item['item'], 'drops' => $item['drops'], 'cells' => $cells,
            'difference' => $pair === [] ? null : RateDifference::between($estimates[$pair[0]->key], $estimates[$pair[1]->key], $compared, $pair[0]->sharesCharacterWith($pair[1])),
        ];
    }

    /**
     * Windows of the arm that listed the item: the corpses with a key, and of the windows with no key its reader's or
     * every character's.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<int, array<int, array<int, int>>>  $readers
     */
    private function listing(int $itemId, LootArm $arm, array $items, array $readers, ArmSplit $split, ?string $chosen): int
    {
        $count = 0;
        if ($arm->reader !== null) {
            foreach ($readers[$itemId] ?? [] as $size => $byCharacter) {
                if ($split === ArmSplit::Rule || (string) $size === $arm->key) {
                    $count += $byCharacter[$arm->reader] ?? 0;
                }
            }
        }
        foreach ($items as $row) {
            if ((int) $row['item_id'] !== $itemId || ($arm->reader !== null && (int) $row['keyed'] !== 1)) {
                continue;
            }
            $size = $row['group_size'] === null ? null : max(1, (int) $row['group_size']);
            $matches = $split === ArmSplit::Rule ? $row['loot_rule'] === $arm->key : $row['loot_rule'] === $chosen && (string) $size === $arm->key;
            $count += $matches ? (int) $row['drops'] : 0;
        }

        return $count;
    }
}
