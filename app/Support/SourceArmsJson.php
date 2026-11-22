<?php

namespace App\Support;

/** One source's drop table split into arms, as the public API answers it (`by_rule` and `by_size`), from the answer of App\Actions\Rollups\SourceRuleArms. */
final class SourceArmsJson
{
    /**
     * @param  array<string, mixed>  $answer  the answer of SourceRuleArms
     * @return array<string, mixed>
     */
    public static function from(array $answer): array
    {
        /** @var ArmSplit $split */
        $split = $answer['split'];
        /** @var list<LootArm> $arms */
        $arms = $answer['arms'];
        /** @var list<LootArm> $pair */
        $pair = $answer['pair'];
        /** @var list<array{item_id: int, item: string|null, drops: int, cells: list<array{arm: LootArm, estimate: RateEstimate}>, difference: RateDifference|null}> $rows */
        $rows = $answer['rows'];

        return [
            'split' => $split->value,
            'rule' => $answer['rule'],
            'rules' => $answer['rules'],
            'sizes' => $answer['sizes'],
            'arms' => $arms,
            'compared' => array_map(fn (LootArm $arm): string => $arm->key, $pair),
            'rows' => array_map(fn (array $row): array => [
                'item_id' => $row['item_id'],
                'item' => $row['item'],
                'drops' => $row['drops'],
                'arms' => array_map(fn (array $cell): array => ['key' => $cell['arm']->key, 'estimate' => $cell['estimate']], $row['cells']),
                'difference' => $row['difference'],
            ], $rows),
        ];
    }
}
