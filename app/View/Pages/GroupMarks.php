<?php

namespace App\View\Pages;

use App\Actions\Rollups\LootMarks;
use App\Support\ArmSplit;
use App\Support\LootArm;
use App\Support\LootRule;
use App\Support\LootSourceRef;
use App\Support\RateDifference;
use App\Support\RateEstimate;
use App\Support\RateFlag;

/** The Marked differences module of the loot rules and group size page, shown at `?marks=1` (docs/LOOT.md, What a mark means). */
final class GroupMarks
{
    /**
     * @param  list<array{source: LootSourceRef, name: string|null, split: ArmSplit, rule: string|null, item_id: int, item: string|null, pair: list<LootArm>, cells: list<array{arm: LootArm, estimate: RateEstimate}>, difference: RateDifference}>|null  $marks  the answer of LootMarks, null when not asked for
     * @return array<string, mixed>|null
     */
    public static function from(?array $marks): ?array
    {
        if ($marks === null) {
            return null;
        }

        return [
            'empty' => $marks === [] ? 'No difference between loot rules or group sizes is marked.' : null,
            'columns' => [['label' => 'Source'], ['label' => 'Item'], ['label' => 'Compared'], ['label' => 'Difference', 'numeric' => true], ['label' => 'Tags']],
            'rows' => array_map(fn (array $mark): array => ['cells' => [
                ['text' => $mark['source']->kind->title($mark['name'], $mark['source']->id), 'href' => $mark['source']->page()],
                ['text' => $mark['item'] ?? 'Item '.$mark['item_id'], 'href' => '/items/'.$mark['item_id']],
                self::compared($mark),
                $mark['difference']->words().', '.$mark['difference']->range(),
                implode(', ', array_map(fn (RateFlag $flag): string => $flag->label(), $mark['difference']->flags)),
            ]], $marks),
            'note' => 'A difference between loot rules or group sizes is a counting fault until shown otherwise: the game\'s drop table does not depend on either. The '.LootMarks::CHECKED.' creatures and objects with the most corpses under two rules or two sizes are checked.',
        ];
    }

    /**
     * `Group loot 41 of 71, Free for all 8 of 42`, and for a size split `Size 2 41 of 71, Size 3 8 of 42 under group loot`.
     *
     * @param  array{split: ArmSplit, rule: string|null, pair: list<LootArm>, cells: list<array{arm: LootArm, estimate: RateEstimate}>}  $mark
     */
    private static function compared(array $mark): string
    {
        $parts = [];
        foreach ($mark['pair'] as $arm) {
            foreach ($mark['cells'] as $cell) {
                if ($cell['arm']->key === $arm->key) {
                    $parts[] = $arm->label.' '.$cell['estimate']->counts();
                }
            }
        }

        return implode(', ', $parts).($mark['split'] === ArmSplit::Size && $mark['rule'] !== null ? ' under '.strtolower(LootRule::words($mark['rule'])) : '');
    }
}
