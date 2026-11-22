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
    private const string WIDE = ' This module covers every creature; a source does not narrow it.';

    /**
     * @param  list<array{rule: string, size: int, yours: int, others: int, estimate: RateEstimate}>  $rows  the answer of GroupShare
     * @return array<string, mixed>
     */
    public static function share(array $rows, bool $narrowed): array
    {
        return [
            'empty' => $rows === [] ? 'No group has received loot under group loot, round robin or need before greed yet.' : null,
            'columns' => [['label' => 'Rule'], ['label' => 'Size', 'numeric' => true], ['label' => 'Yours', 'numeric' => true], ['label' => 'Others', 'numeric' => true], ['label' => 'Your share', 'numeric' => true], ['label' => 'Equal share', 'numeric' => true]],
            'rows' => array_map(fn (array $row): array => ['cells' => [
                LootRule::words($row['rule']), (string) $row['size'], Format::number($row['yours']), Format::number($row['others']),
                ['rate' => $row['estimate']], Format::percent(1 / $row['size'], 1),
            ]], $rows),
            'note' => 'Yours is what the uploaders received from corpses. Others is what the uploaders\' group mates received from corpses, as the client reported it; an item pushed to a mate is left out. Pooled over uploaders, so each member\'s mean share is the figure. Only the rules that owe every member an equal share are listed; under free for all and master looter nothing does.'.($narrowed ? self::WIDE : ''),
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
            'empty' => $rolls['choices'] === [] ? 'No roll of an uploader is recorded yet.' : null,
            'columns' => [['label' => 'Rule'], ['label' => 'Item quality'], ['label' => 'Rolls', 'numeric' => true], ['label' => 'Need', 'numeric' => true], ['label' => 'Greed', 'numeric' => true], ['label' => 'Pass', 'numeric' => true]],
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
            'note' => 'Rolls of the characters that uploaded. A roll another member made reaches every uploader in the group, so it is counted by none of them. A rate is from 10 rolls; under that the cell is empty.'.($narrowed ? self::WIDE : ''),
        ];
    }
}
