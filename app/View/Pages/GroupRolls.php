<?php

namespace App\View\Pages;

use App\Support\ItemQuality;
use App\Support\LootRule;
use App\Support\RateEstimate;
use App\Support\RateSample;
use App\View\Format;

/** The Your share of group loot and Rolls modules of the loot rules and group size page (docs/comps/groups.html). */
final class GroupRolls
{
    /**
     * @param  list<array{rule: string, size: int, yours: int, others: int, estimate: RateEstimate}>  $rows  the answer of GroupShare
     * @return array<string, mixed>
     */
    public static function share(array $rows, bool $narrowed): array
    {
        return [
            'heading' => 'Your share of group loot'.($narrowed ? ', all creatures' : ''),
            'empty' => $rows === [] ? 'No group has received loot under group loot, round robin or need before greed yet.' : null,
            'columns' => [['label' => 'Rule'], ['label' => 'Size', 'numeric' => true], ['label' => 'Yours', 'numeric' => true], ['label' => 'Others', 'numeric' => true], ['label' => 'Your share', 'numeric' => true], ['label' => 'Equal share', 'numeric' => true]],
            'rows' => array_map(fn (array $row): array => ['cells' => [
                LootRule::words($row['rule']), (string) $row['size'], Format::number($row['yours']), Format::number($row['others']),
                ['rate' => $row['estimate']], Format::percent(1 / $row['size'], 1),
            ]], $rows),
        ];
    }

    /**
     * @param  array{choices: list<array{rule: string, quality: int|null, need: int, greed: int, pass: int}>, thresholds: list<array{rule: string, threshold: int, corpses: int, items: int}>}  $rolls  the answer of LootRolls
     * @return array<string, mixed>
     */
    public static function rolls(array $rolls, bool $narrowed): array
    {
        $quality = fn (?int $value): string => ($value === null ? null : ItemQuality::tryFrom($value)?->label()) ?? 'Unknown';

        return [
            'heading' => 'Rolls'.($narrowed ? ', all creatures' : ''),
            'empty' => $rolls['choices'] === [] ? 'No roll of an uploader is recorded yet.' : null,
            'columns' => [['label' => 'Rule'], ['label' => 'Item quality'], ['label' => 'Uploader rolls', 'numeric' => true], ['label' => 'Need', 'numeric' => true], ['label' => 'Greed', 'numeric' => true], ['label' => 'Pass', 'numeric' => true]],
            'rows' => array_map(function (array $row) use ($quality): array {
                $total = $row['need'] + $row['greed'] + $row['pass'];

                return ['cells' => [
                    LootRule::words($row['rule']), $quality($row['quality']), Format::number($total),
                    ['rate' => RateEstimate::of($row['need'], $total, [], RateEstimate::UNIT_ROLL)],
                    ['rate' => RateEstimate::of($row['greed'], $total, [], RateEstimate::UNIT_ROLL)],
                    ['rate' => RateEstimate::of($row['pass'], $total, [], RateEstimate::UNIT_ROLL)],
                ]];
            }, $rolls['choices']),
            'thresholdColumns' => [['label' => 'Rule'], ['label' => 'Threshold', 'numeric' => true], ['label' => 'Corpses in groups', 'numeric' => true], ['label' => 'Items rolled for per 100 corpses', 'numeric' => true]],
            'thresholds' => array_map(fn (array $row): array => ['cells' => [
                LootRule::words($row['rule']), $quality($row['threshold']), Format::number($row['corpses']),
                $row['corpses'] >= RateSample::HOLLOW_FROM ? Format::number($row['items'] / $row['corpses'] * 100, 1) : '–',
            ]], $rolls['thresholds']),
        ];
    }
}
