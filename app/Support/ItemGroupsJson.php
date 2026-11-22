<?php

namespace App\Support;

/** What groups did with one item, as the public API answers it (`rolls` and `receivers`), from the answer of App\Actions\Public\ItemGroups. */
final class ItemGroupsJson
{
    /**
     * @param  array{rolls: array{need: int, greed: int, pass: int, all_passed: int}|null, receivers: list<array{class: string, received: int, rolled: int, won: int}>}  $groups
     * @return array{rolls: array<string, mixed>|null, receivers: list<array<string, mixed>>}
     */
    public static function from(array $groups): array
    {
        $rolls = $groups['rolls'];
        $total = $rolls === null ? 0 : $rolls['need'] + $rolls['greed'] + $rolls['pass'];
        $received = array_sum(array_column($groups['receivers'], 'received'));

        return [
            'rolls' => $rolls === null ? null : [
                'rolls' => $total, 'all_passed' => $rolls['all_passed'],
                'need' => RateEstimate::of($rolls['need'], $total, [], RateEstimate::UNIT_ROLL),
                'greed' => RateEstimate::of($rolls['greed'], $total, [], RateEstimate::UNIT_ROLL),
                'pass' => RateEstimate::of($rolls['pass'], $total, [], RateEstimate::UNIT_ROLL),
            ],
            'receivers' => array_map(fn (array $row): array => [
                'class' => $row['class'], 'received' => $row['received'],
                'share' => RateEstimate::of($row['received'], $received, [], RateEstimate::UNIT_RECEIPT),
                'rolled' => $row['rolled'], 'won' => $row['won'],
                'won_by_roll' => RateEstimate::of($row['won'], $row['rolled'], [], RateEstimate::UNIT_ROLL),
            ], $groups['receivers']),
        ];
    }
}
