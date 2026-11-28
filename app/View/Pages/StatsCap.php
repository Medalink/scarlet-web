<?php

namespace App\View\Pages;

use App\View\Format;

/** The "At level 60" table of /stats: what characters at the level cap have recorded since they were first seen. */
final class StatsCap
{
    /**
     * @param  array<string, mixed>  $r
     * @param  list<array<string, mixed>>  $reached
     * @return array{heading: string, rows: list<array{cells: list<string>}>, note: string|null}
     */
    public static function of(array $r, array $reached, int $target): array
    {
        $totals = StatsPage::totals($r['totals']);
        $at = (int) (array_column($reached, 'characters', 'level')[$target] ?? 0);
        $row = fn (string $label, string $value, string $basis): array => ['cells' => [$label, $value, $basis]];
        $figure = function (string $label, ?array $data, callable $value) use ($row): array {
            return $data === null ? $row($label, '–', Format::TOO_FEW) : $row($label, $value($data), Format::count((int) $data['characters'], 'character'));
        };
        $cap = is_array($r['at_cap'] ?? null) ? $r['at_cap'] : [];
        $money = $cap['money'] ?? null;
        $purse = fn (string $label, string $key): array => $money === null
            ? $row($label, '–', 'No economy data shared')
            : $row($label, Format::moneyText((float) $money[$key]), Format::count((int) $money['characters'], 'character'));

        return [
            'heading' => 'At level '.$target.', since first seen',
            'rows' => $at === 0 ? [] : [
                $row('Characters', Format::number($at), $totals['characters'] === null ? Format::TOO_FEW : 'of '.Format::count((int) $totals['characters'], 'character')),
                $figure('Recorded time', $cap['seconds'] ?? null, fn (array $d): string => Format::duration((float) $d['seconds'])),
                $purse('Gold earned', 'earned'),
                $purse('Gold spent', 'spent'),
                $figure('Dungeon runs', $cap['runs'] ?? null, fn (array $d): string => Format::number((int) $d['runs'])),
                $figure('Deaths', $cap['deaths'] ?? null, fn (array $d): string => Format::number((int) $d['deaths'])),
            ],
            'note' => $at === 0 ? 'No character is at level '.$target.' yet.' : null,
        ];
    }
}
