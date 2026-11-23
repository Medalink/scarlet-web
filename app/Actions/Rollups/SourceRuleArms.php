<?php

namespace App\Actions\Rollups;

use App\Support\ArmSplit;
use App\Support\ItemBind;
use App\Support\LootArm;
use App\Support\LootRule;
use App\Support\LootSourceKind;
use App\Support\LootSourceRef;
use App\Support\RateDifference;
use App\Support\RateEstimate;
use App\Support\RateFlag;
use App\Support\SourceArmData;

class SourceRuleArms
{
    /** An item is a row of a chart from this many drops over the arms in scope. */
    private const int CHART_FROM_DROPS = 10;

    public function __construct(private readonly SourceArmRows $rows) {}

    /**
     * One source's drop table split into arms, by loot rule or by group size inside one rule (docs/LOOT.md, Creature
     * drops): a row for each item with 10 drops or more, and the rate in each arm that has 10 windows or more, over
     * windows read for loot, each corpse with a key once (docs/LOOT.md, Units of counting, rule 7). A free-for-all arm with windows with no key that a second character contributed to counts one reader's set, and the
     * larger of two sets only on the days where both exist (`LootArm::overDays`). The two
     * drawn arms with the most windows, never personal loot, are compared, and each row carries their difference over
     * the items of the chart. `rules` and `sizes` list what was recorded, for the empty states; `rule` is the rule a
     * size split reads (`$rule` when the source has windows under it, else the rule with the most windows). `$data` is
     * the rows of the source when the caller has read those of many sources at once (`SourceArmRows`).
     *
     * @return array{split: ArmSplit, rule: string|null, rules: list<array{rule: string, windows: int}>, sizes: list<array{size: int, windows: int}>, arms: list<LootArm>, pair: list<LootArm>, rows: list<array{item_id: int, item: string|null, drops: int, cells: list<array{arm: LootArm, estimate: RateEstimate}>, difference: RateDifference|null}>}
     */
    public function handle(string $sourceKind, int $sourceId, ArmSplit $split, ?string $rule = null, ?SourceArmData $data = null): array
    {
        $source = new LootSourceRef(LootSourceKind::from($sourceKind), $sourceId);
        $data ??= $this->rows->handle([$source])[$source->key()];
        $windows = $data->windows;
        $rules = $this->rulesSeen($windows);
        $chosen = $split === ArmSplit::Size ? $this->sizeRule($rules, $rule) : null;
        $arms = $this->arms($split, $windows, $chosen, $data->midnights);
        $pair = $this->pair($arms);
        $sizes = $chosen === null ? [] : $this->sizesSeen($windows, $chosen);
        $answer = ['split' => $split, 'rule' => $chosen, 'rules' => $rules, 'sizes' => $sizes, 'arms' => $arms, 'pair' => $pair, 'rows' => []];
        $drawn = array_values(array_filter($arms, fn (LootArm $arm): bool => $arm->drawn()));
        if (count($drawn) < 2) {
            return $answer;
        }

        $listed = $this->listed($data->items, $split, $chosen);
        $byItem = [];
        foreach ($data->items as $row) {
            $byItem[(int) $row['item_id']][] = $row;
        }
        $answer['rows'] = array_map(fn (array $item): array => $this->row($item, $drawn, $pair, count($listed), $byItem, $data->readers, $split, $chosen), $listed);

        return $answer;
    }

    /**
     * @param  list<array{loot_rule: string, size: int|null, character: int, keyed: bool, day: string, windows: int}>  $windows
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
     * @param  list<array{loot_rule: string, size: int|null, character: int, keyed: bool, day: string, windows: int}>  $windows
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
     * @param  list<array{loot_rule: string, size: int|null, character: int, keyed: bool, day: string, windows: int}>  $windows
     * @param  list<string>  $joined
     * @return list<LootArm>
     */
    private function arms(ArmSplit $split, array $windows, ?string $chosen, array $joined): array
    {
        $groups = [];
        foreach ($windows as $row) {
            if ($split === ArmSplit::Size && ($row['loot_rule'] !== $chosen || $row['size'] === null)) {
                continue;
            }
            $key = $split === ArmSplit::Rule ? $row['loot_rule'] : (string) $row['size'];
            $part = $row['keyed'] ? 'keyed' : 'unkeyed';
            $groups[$key][$row['day']]['unkeyed'] ??= [];
            $groups[$key][$row['day']]['keyed'] ??= [];
            $groups[$key][$row['day']][$part][$row['character']] = ($groups[$key][$row['day']][$part][$row['character']] ?? 0) + $row['windows'];
        }
        $arms = [];
        foreach ($groups as $key => $days) {
            $key = (string) $key;
            $rule = $split === ArmSplit::Rule ? $key : (string) $chosen;
            $arm = LootArm::overDays(
                $key, $split === ArmSplit::Rule ? LootRule::words($key) : 'Size '.$key, $days,
                $rule === LootRule::FreeForAll->value, $split === ArmSplit::Rule && $rule === LootRule::Personal->value, $rule === LootRule::Unknown->value,
                (int) config('scarlet.min_characters'), $joined,
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
     * @param  array<int, list<array<string, mixed>>>  $byItem  the item rows by item
     * @param  array<int, array<int, array<string, array<int, int>>>>  $readers
     * @return array{item_id: int, item: string|null, drops: int, cells: list<array{arm: LootArm, estimate: RateEstimate}>, difference: RateDifference|null}
     */
    private function row(array $item, array $drawn, array $pair, int $compared, array $byItem, array $readers, ArmSplit $split, ?string $chosen): array
    {
        $quest = $item['bind_type'] === ItemBind::Quest->value ? [RateFlag::QuestItem] : [];
        $cells = array_map(fn (LootArm $arm): array => [
            'arm' => $arm,
            'estimate' => RateEstimate::of($this->listing($item['item_id'], $arm, $byItem, $readers, $split, $chosen), $arm->windows, [...$arm->flags, ...$quest]),
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
     * Windows of the arm that listed the item (`LootArm::dropsOver`): each day, the corpses with a key and of the windows
     * with no key its reader's or every character's, whichever the arm counts that day.
     *
     * @param  array<int, list<array<string, mixed>>>  $byItem
     * @param  array<int, array<int, array<string, array<int, int>>>>  $readers
     */
    private function listing(int $itemId, LootArm $arm, array $byItem, array $readers, ArmSplit $split, ?string $chosen): int
    {
        $days = [];
        foreach ($byItem[$itemId] ?? [] as $row) {
            $size = $row['group_size'] === null ? null : max(1, (int) $row['group_size']);
            $matches = $split === ArmSplit::Rule ? $row['loot_rule'] === $arm->key : $row['loot_rule'] === $chosen && (string) $size === $arm->key;
            if (! $matches) {
                continue;
            }
            $day = (string) $row['day'];
            $days[$day] ??= ['keyed' => 0, 'unkeyed' => 0, 'reader' => 0];
            $days[$day][(int) $row['keyed'] === 1 ? 'keyed' : 'unkeyed'] += (int) $row['drops'];
        }
        if ($arm->reader !== null) {
            foreach ($readers[$itemId] ?? [] as $size => $byDay) {
                if ($split === ArmSplit::Rule || (string) $size === $arm->key) {
                    foreach ($byDay as $day => $byCharacter) {
                        $days[(string) $day] ??= ['keyed' => 0, 'unkeyed' => 0, 'reader' => 0];
                        $days[(string) $day]['reader'] += $byCharacter[$arm->reader] ?? 0;
                    }
                }
            }
        }

        return $arm->dropsOver($days);
    }
}
