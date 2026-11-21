<?php

namespace App\Support;

/** The `ranks` column of a talent snapshot: `node:entry:rank:spell:tab` for every node holding a rank, comma-joined. */
final class TalentRanks
{
    /** @return array<int, array{node: int, entry: int, rank: int, spell: int|null, tab: int|null}> by node */
    public static function parse(string $ranks): array
    {
        $held = [];
        foreach (explode(',', $ranks) as $item) {
            $part = explode(':', $item);
            if (count($part) < 3) {
                continue;
            }
            $held[(int) $part[0]] = [
                'node' => (int) $part[0],
                'entry' => (int) $part[1],
                'rank' => (int) $part[2],
                'spell' => isset($part[3]) && $part[3] !== '' ? (int) $part[3] : null,
                'tab' => isset($part[4]) && $part[4] !== '' ? (int) $part[4] : null,
            ];
        }

        return $held;
    }

    /** @param  array<int, array{rank: int}>  $held */
    public static function points(array $held): int
    {
        return array_sum(array_column($held, 'rank'));
    }

    /**
     * The tabs of a snapshot's `tab_points`: `groupID:spent:available`, comma-joined.
     *
     * @return list<array{id: int, spent: int}>
     */
    public static function tabs(?string $tabPoints): array
    {
        $tabs = [];
        foreach (explode(',', (string) $tabPoints) as $item) {
            $part = explode(':', $item);
            if ($part[0] === '') {
                continue;
            }
            $tabs[] = ['id' => (int) $part[0], 'spent' => (int) ($part[1] ?? 0)];
        }

        return $tabs;
    }
}
