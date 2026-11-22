<?php

namespace App\View\Pages;

use App\Support\ArmorType;
use App\Support\RateEstimate;
use App\Support\RateSample;
use App\View\Format;

/** The Group composition module of the loot rules and group size page (docs/comps/groups.html). */
final class GroupCompositionTables
{
    /**
     * @param  array{need: list<array{class: string, armor: int, need: int, greed: int, pass: int}>, skins: list<array{skinner: bool, looted: int, skinned: int}>, roles: list<array{tank: bool, healer: bool, kills: int, seconds: int}>}  $answer  the answer of GroupComposition
     * @return array<string, mixed>
     */
    public static function from(array $answer, bool $narrowed): array
    {
        $yes = fn (bool $value): string => $value ? 'Yes' : 'No';

        return [
            'heading' => 'Group composition'.($narrowed ? ', all creatures' : ''),
            'empty' => $answer['need'] === [] && $answer['skins'] === [] && $answer['roles'] === [] ? 'No group has recorded the classes or roles of its members yet.' : null,
            'need' => $answer['need'] === [] ? null : [
                'columns' => [['label' => 'Class'], ['label' => 'Armour'], ['label' => 'Rolls', 'numeric' => true], ['label' => 'Need', 'numeric' => true]],
                'rows' => array_map(function (array $row): array {
                    $total = $row['need'] + $row['greed'] + $row['pass'];

                    return ['cells' => [
                        ['text' => Format::className($row['class']), 'dot' => true, 'characterClass' => $row['class']],
                        ArmorType::from($row['armor'])->name, Format::number($total), ['rate' => RateEstimate::of($row['need'], $total, [], RateEstimate::UNIT_ROLL)],
                    ]];
                }, $answer['need']),
            ],
            'skins' => $answer['skins'] === [] ? null : [
                'columns' => [['label' => 'Uploader skins'], ['label' => 'Corpses looted', 'numeric' => true], ['label' => 'Corpses skinned', 'numeric' => true], ['label' => 'Skins per corpse', 'numeric' => true]],
                'rows' => array_map(fn (array $row): array => ['cells' => [
                    $yes($row['skinner']), Format::number($row['looted']), Format::number($row['skinned']),
                    $row['looted'] >= RateSample::HOLLOW_FROM ? Format::number($row['skinned'] / $row['looted'], 2) : '–',
                ]], $answer['skins']),
            ],
            'roles' => $answer['roles'] === [] ? null : [
                'columns' => [['label' => 'Tank'], ['label' => 'Healer'], ['label' => 'Kills', 'numeric' => true], ['label' => 'Hours in a group', 'numeric' => true], ['label' => 'Kills an hour', 'numeric' => true]],
                'rows' => array_map(fn (array $row): array => ['cells' => [
                    $yes($row['tank']), $yes($row['healer']), Format::number($row['kills']), Format::number($row['seconds'] / 3600, 1), GroupKillRates::perHour($row['kills'], $row['seconds']),
                ]], $answer['roles']),
            ],
        ];
    }
}
