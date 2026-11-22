<?php

namespace App\Support;

/** The answer of `/public/loot/groups`, from the answer of App\Actions\Public\LootGroups: every rate is its `estimate`, and a figure under its sample is `null`. */
final class LootGroupsJson
{
    /**
     * @param  array<string, mixed>  $answer  the answer of LootGroups
     * @return array<string, mixed>
     */
    public static function from(array $answer): array
    {
        /** @var LootSourceRef|null $source */
        $source = $answer['source'];
        /** @var list<array{rule: string, size: int, yours: int, others: int, estimate: RateEstimate}> $share */
        $share = $answer['share'];
        /** @var array{choices: list<array{rule: string, quality: int|null, need: int, greed: int, pass: int}>, thresholds: list<array{rule: string, threshold: int, corpses: int, items: int}>} $rolls */
        $rolls = $answer['rolls'];
        /** @var list<array{size: int, kills: int, seconds: int}> $kills */
        $kills = $answer['kills'];
        /** @var array{need: list<array{class: string, armor: int, need: int, greed: int, pass: int}>, skins: list<array{skinner: bool, looted: int, skinned: int}>, roles: list<array{tank: bool, healer: bool, kills: int, seconds: int}>} $composition */
        $composition = $answer['composition'];
        /** @var list<array{source: LootSourceRef, name: string|null, split: ArmSplit, rule: string|null, item_id: int, item: string|null, pair: list<LootArm>, cells: list<array{arm: LootArm, estimate: RateEstimate}>, difference: RateDifference}>|null $marks */
        $marks = $answer['marks'];

        return [
            'source' => $source === null ? null : ['source_kind' => $source->kind->value, 'source_id' => $source->id, 'name' => $answer['source_name'], 'path' => $source->page()],
            'figures' => $answer['figures'],
            'effects' => $answer['effects'],
            'kept' => $answer['kept'],
            'share' => array_map(fn (array $row): array => ['rule' => $row['rule'], 'size' => $row['size'], 'yours' => $row['yours'], 'others' => $row['others'], 'equal_share' => round(1 / $row['size'], 4), 'estimate' => $row['estimate']], $share),
            'rolls' => [
                'choices' => array_map(fn (array $row): array => [
                    'rule' => $row['rule'], 'quality' => $row['quality'], 'rolls' => $row['need'] + $row['greed'] + $row['pass'],
                    'need' => self::roll($row['need'], $row), 'greed' => self::roll($row['greed'], $row), 'pass' => self::roll($row['pass'], $row),
                ], $rolls['choices']),
                'thresholds' => array_map(fn (array $row): array => [
                    'rule' => $row['rule'], 'threshold' => $row['threshold'], 'corpses' => $row['corpses'], 'items' => $row['items'],
                    'per_100_corpses' => $row['corpses'] >= RateSample::HOLLOW_FROM ? round($row['items'] / $row['corpses'] * 100, 2) : null,
                ], $rolls['thresholds']),
            ],
            'kills_by_size' => array_map(fn (array $row): array => ['size' => $row['size'], 'kills' => $row['kills'], 'hours' => round($row['seconds'] / 3600, 2), 'per_hour' => self::perHour($row['kills'], $row['seconds'])], $kills),
            'composition' => [
                'need' => array_map(fn (array $row): array => [
                    'class' => $row['class'], 'armor' => strtolower(ArmorType::from($row['armor'])->name), 'rolls' => $row['need'] + $row['greed'] + $row['pass'],
                    'need' => self::roll($row['need'], $row),
                ], $composition['need']),
                'skins' => array_map(fn (array $row): array => [
                    'uploader_skins' => $row['skinner'], 'looted' => $row['looted'], 'skinned' => $row['skinned'],
                    'per_corpse' => $row['looted'] >= RateSample::HOLLOW_FROM ? round($row['skinned'] / $row['looted'], 3) : null,
                ], $composition['skins']),
                'roles' => array_map(fn (array $row): array => [
                    'tank' => $row['tank'], 'healer' => $row['healer'], 'kills' => $row['kills'], 'hours' => round($row['seconds'] / 3600, 2), 'per_hour' => self::perHour($row['kills'], $row['seconds']),
                ], $composition['roles']),
            ],
            'marks' => $marks === null ? null : array_map(fn (array $mark): array => [
                'source_kind' => $mark['source']->kind->value, 'source_id' => $mark['source']->id, 'name' => $mark['name'], 'path' => $mark['source']->page(),
                'split' => $mark['split']->value, 'rule' => $mark['rule'], 'item_id' => $mark['item_id'], 'item' => $mark['item'],
                'arms' => self::arms($mark['pair'], $mark['cells']), 'difference' => $mark['difference'],
            ], $marks),
        ];
    }

    /** @param  array{need: int, greed: int, pass: int}  $row */
    private static function roll(int $count, array $row): RateEstimate
    {
        return RateEstimate::of($count, $row['need'] + $row['greed'] + $row['pass'], [], RateEstimate::UNIT_ROLL);
    }

    private static function perHour(int $kills, int $seconds): ?float
    {
        return $kills >= RateSample::HOLLOW_FROM && $seconds > 0 ? round($kills / ($seconds / 3600), 2) : null;
    }

    /**
     * The two arms compared, each with its estimate for the item.
     *
     * @param  list<LootArm>  $pair
     * @param  list<array{arm: LootArm, estimate: RateEstimate}>  $cells
     * @return list<array<string, mixed>>
     */
    private static function arms(array $pair, array $cells): array
    {
        $arms = [];
        foreach ($pair as $arm) {
            foreach ($cells as $cell) {
                if ($cell['arm']->key === $arm->key) {
                    $arms[] = [...$arm->jsonSerialize(), 'estimate' => $cell['estimate']];
                }
            }
        }

        return $arms;
    }
}
