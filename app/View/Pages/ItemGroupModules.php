<?php

namespace App\View\Pages;

use App\Support\RateEstimate;
use App\Support\RateSample;
use App\View\Format;

/** The Rolls in groups and Who received it modules of an item page (docs/comps/item.html), from the answer of App\Actions\Public\ItemGroups. */
final class ItemGroupModules
{
    /**
     * @param  array{rolls: array{need: int, greed: int, pass: int, all_passed: int}|null, receivers: list<array{class: string, received: int, rolled: int, won: int}>}  $groups
     * @return array{rolls: array<string, mixed>|null, received: array<string, mixed>|null}
     */
    public static function from(array $groups): array
    {
        return ['rolls' => self::rolls($groups['rolls']), 'received' => self::received($groups['receivers'])];
    }

    /**
     * @param  array{need: int, greed: int, pass: int, all_passed: int}|null  $rolls
     * @return array<string, mixed>|null
     */
    private static function rolls(?array $rolls): ?array
    {
        if ($rolls === null) {
            return null;
        }
        $total = $rolls['need'] + $rolls['greed'] + $rolls['pass'];
        $rows = [];
        foreach (['Need' => $rolls['need'], 'Greed' => $rolls['greed'], 'Pass' => $rolls['pass']] as $label => $count) {
            $estimate = RateEstimate::of($count, $total, [], RateEstimate::UNIT_ROLL);
            $rows[] = ['label' => $label, 'value' => $estimate->stated() ? $estimate->percent().' · '.$estimate->counts() : $estimate->counts(), 'share' => $estimate->stated() ? $estimate->rate * 100 : null];
        }

        return [
            'rows' => $rows,
            'note' => Format::number($rolls['all_passed']).' of the '.Format::number($total).' rolls were passed by everyone.',
        ];
    }

    /**
     * @param  list<array{class: string, received: int, rolled: int, won: int}>  $receivers
     * @return array<string, mixed>|null
     */
    private static function received(array $receivers): ?array
    {
        if ($receivers === []) {
            return null;
        }
        $total = array_sum(array_column($receivers, 'received'));

        return [
            'columns' => [['label' => 'Class'], ['label' => 'Received', 'numeric' => true], ['label' => 'Share', 'numeric' => true], ['label' => 'Won by roll', 'numeric' => true]],
            'rows' => array_map(fn (array $row): array => ['cells' => [
                ['text' => Format::className($row['class']), 'dot' => true, 'characterClass' => $row['class']],
                Format::number($row['received']),
                $total >= RateSample::HOLLOW_FROM ? Format::percent($row['received'] / $total, 1) : '–',
                $row['rolled'] > 0 ? Format::number($row['won']).' of '.Format::number($row['rolled']) : '–',
            ]], $receivers),
            'note' => 'A group with several uploaders counts once for each.',
        ];
    }
}
