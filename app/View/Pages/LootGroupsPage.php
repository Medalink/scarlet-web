<?php

namespace App\View\Pages;

use App\Support\ArmSplit;
use App\Support\LootArm;
use App\Support\LootRule;
use App\Support\LootSourceKind;
use App\Support\LootSourceRef;
use App\Support\RateDifference;
use App\Support\RateEstimate;
use App\Support\RateSample;
use App\View\Format;

/** The data of the loot rules and group size page (docs/comps/groups.html) from the answer of App\Actions\Public\LootGroups. */
final class LootGroupsPage
{
    /**
     * @param  array<string, mixed>  $r  the answer of LootGroups
     * @return array<string, mixed>
     */
    public static function from(array $r): array
    {
        /** @var LootSourceRef|null $source */
        $source = $r['source'];
        /** @var array{corpses: int, rules: int, largest: int, characters: int, first_at: string|null, last_at: string|null, several: int, rolls: int, groups: int} $figures */
        $figures = $r['figures'];
        /** @var array<string, mixed> $effects */
        $effects = $r['effects'];
        /** @var list<array{rule: string, size: int, corpses: int, items: int, received: int, other: bool}> $kept */
        $kept = $r['kept'];
        /** @var list<array{rule: string, size: int, yours: int, others: int, estimate: RateEstimate}> $share */
        $share = $r['share'];
        /** @var array{choices: list<array{rule: string, quality: int|null, need: int, greed: int, pass: int}>, thresholds: list<array{rule: string, threshold: int, corpses: int, items: int}>} $rolls */
        $rolls = $r['rolls'];
        /** @var list<array{size: int, kills: int, seconds: int}> $kills */
        $kills = $r['kills'];
        /** @var array{need: list<array{class: string, armor: int, need: int, greed: int, pass: int}>, skins: list<array{skinner: bool, looted: int, skinned: int}>, roles: list<array{tank: bool, healer: bool, kills: int, seconds: int}>} $composition */
        $composition = $r['composition'];
        /** @var list<array{source: LootSourceRef, name: string|null, split: ArmSplit, rule: string|null, item_id: int, item: string|null, pair: list<LootArm>, cells: list<array{arm: LootArm, estimate: RateEstimate}>, difference: RateDifference}>|null $marks */
        $marks = $r['marks'];

        return [
            'sub' => self::sub($figures),
            'tabs' => LootPage::views(null),
            'none' => $figures['rules'] === 0,
            'source' => $source === null ? null : ['name' => $source->kind->title(is_string($r['source_name']) ? $r['source_name'] : null, $source->id), 'href' => $source->page()],
            'figures' => array_map(fn (array $tile): array => ['label' => $tile[0], 'value' => Format::number($tile[1])], array_values(array_filter([
                ['Corpses looted', $figures['corpses']],
                ['Loot rules seen', $figures['rules']],
                ['Largest group', $figures['largest']],
                ...($source === null
                    ? [['Creatures under two rules', $figures['several']], ['Rolls', $figures['rolls']], ['Groups recorded', $figures['groups']]]
                    : [['Characters', $figures['characters']]]),
            ], fn (array $tile): bool => $tile[1] > 0))),
            'effects' => self::effects($effects, $source),
            'kept' => self::kept($kept),
            'share' => GroupRolls::share($share, $source !== null),
            'rolls' => GroupRolls::rolls($rolls, $source !== null),
            'kills' => GroupKillRates::bySize($kills, $source !== null),
            'composition' => GroupCompositionTables::from($composition, $source !== null),
            'marks' => GroupMarks::from($marks),
        ];
    }

    /**
     * `874 corpses looted under 4 loot rules, in groups of up to 5.`
     *
     * @param  array{corpses: int, rules: int, largest: int, characters: int, first_at: string|null, last_at: string|null, several: int, rolls: int, groups: int}  $figures
     */
    private static function sub(array $figures): string
    {
        if ($figures['corpses'] === 0 || $figures['first_at'] === null || $figures['last_at'] === null) {
            return 'No corpse looted under a loot rule is recorded yet.';
        }

        return Format::count($figures['corpses'], 'corpse').' looted'.($figures['rules'] > 0 ? ' under '.Format::count($figures['rules'], 'loot rule') : '')
            .($figures['largest'] > 1 ? ', in groups of up to '.Format::number($figures['largest']) : '').'.';
    }

    /**
     * @param  array<string, mixed>  $e
     * @return array<string, mixed>
     */
    private static function effects(array $e, ?LootSourceRef $source): array
    {
        /** @var list<array{rule: string, windows: int, sources: int}> $seen */
        $seen = $e['seen'];
        /** @var list<string> $pair */
        $pair = $e['pair'];
        /** @var list<array{rule: string, items: float, corpses: int}> $rows */
        $rows = $e['rows'];
        /** @var array{difference: float, low: float, high: float}|null $difference */
        $difference = $e['difference'];
        $unit = $source?->kind === LootSourceKind::Object ? 'object' : 'creature';
        $words = fn (string $rule): string => strtolower(LootRule::words($rule));

        $empty = match (true) {
            count($pair) < 2 => $source === null
                ? 'Fewer than two loot rules have been seen on one creature yet.'
                : 'This '.$unit.' has been looted under fewer than two loot rules.',
            $rows === [] && $source !== null => 'This '.$unit.' has fewer than '.RateSample::HOLLOW_FROM.' corpses under '.$words($pair[0]).' or '.$words($pair[1]).'.',
            $rows === [] => 'No '.$unit.' has '.RateSample::HOLLOW_FROM.' corpses or more under both '.$words($pair[0]).' and '.$words($pair[1]).' yet'
                .($e['more'] > 0 ? '; '.Format::count($e['more'], $unit).' '.($e['more'] === 1 ? 'was' : 'were').' seen under both with fewer.' : '.'),
            default => null,
        };

        return [
            'empty' => $empty,
            'columns' => [['label' => 'Rule'], ['label' => 'Items per corpse', 'numeric' => true], ['label' => 'Corpses', 'numeric' => true], ['label' => $source === null ? 'Creatures' : 'Sources', 'numeric' => true]],
            'rows' => array_map(fn (array $row): array => ['cells' => [LootRule::words($row['rule']), Format::number($row['items'], 2), Format::number($row['corpses']), Format::number($e['sources'])]], $rows),
            'note' => $difference === null || $rows === [] ? null : self::effectNote($pair, $difference, (int) $e['sources'], (int) $e['windows'], $unit),
        ];
    }

    /**
     * @param  list<string>  $pair
     * @param  array{difference: float, low: float, high: float}  $difference
     */
    private static function effectNote(array $pair, array $difference, int $sources, int $windows, string $unit): string
    {
        $range = number_format($difference['low'], 2).' to '.number_format($difference['high'], 2);
        $first = strtolower(LootRule::words($pair[0]));
        $second = strtolower(LootRule::words($pair[1]));
        $stated = $difference['low'] > 0 || $difference['high'] < 0
            ? ucfirst($second).' holds '.number_format(abs($difference['difference']), 2).' items '.($difference['difference'] > 0 ? 'more' : 'fewer').' per corpse than '.$first.', range '.$range.'.'
            : 'No difference found between '.$first.' and '.$second.': '.($difference['difference'] < 0 ? '-' : '+').number_format(abs($difference['difference']), 2).' items per corpse, range '.$range.'.';

        return $stated.' Over '.Format::count($sources, $unit).', '.Format::count($windows, 'corpse').'.';
    }

    /**
     * @param  list<array{rule: string, size: int, corpses: int, items: int, received: int, other: bool}>  $kept
     * @return array<string, mixed>
     */
    private static function kept(array $kept): array
    {
        $known = array_values(array_filter($kept, fn (array $row): bool => $row['rule'] !== LootRule::Unknown->value));

        return [
            'empty' => $known === [] ? 'No corpse looted under a loot rule is recorded yet.' : null,
            'columns' => [['label' => 'Rule'], ['label' => 'Size', 'numeric' => true], ['label' => 'Corpses', 'numeric' => true], ['label' => 'Kept', 'numeric' => true], ['label' => 'Items', 'numeric' => true]],
            'rows' => array_map(fn (array $row): array => ['cells' => [
                ['text' => LootRule::words($row['rule']), 'tag' => $row['other'] ? 'other creatures' : null, 'plainTag' => true],
                (string) $row['size'],
                Format::number($row['corpses']),
                $row['items'] >= RateSample::HOLLOW_FROM ? Format::percent($row['received'] / $row['items']) : '–',
                Format::number($row['received']).' of '.Format::number($row['items']),
            ]], $known),
        ];
    }
}
