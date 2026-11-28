<?php

namespace App\View;

use App\Support\TalentTabOrder;

/**
 * A class talent tree as the in-game window draws it: square slots on a grid with arrows between them. `cells()` places
 * each node of a layout (App\Actions\Public\TalentLayout) on the grid the way the app's talent view does
 * (scarlet-app companion/src/talents.rs, `laid_out`), and `frame()` turns one tree's cells into the pixels of the
 * app's tree frame (app/ui/views/tree-frame.js).
 */
final class TalentFrame
{
    public const int COLS = 4;

    public const int ROWS = 7;

    public const int SLOT = 48;

    public const int GAP = 24;

    private const int PITCH = self::SLOT + self::GAP;

    private const int CELL = 600;

    private const int TYPO_FACTOR = 3;

    private const int HEAD = 7;

    /**
     * Every node of the layout on a grid cell, a tree for each talent tab, in the order of `App\Support\TalentTabOrder`. A coordinate that is ten
     * times too large for its tab is read back as the client meant it. Nodes without a spell entry or a tab are left out,
     * and so are tabs outside `$shown` when it is not empty. A node keeps only the edges that end in its own tab.
     *
     * @param  array{nodes: array<int, array{id: int, x: int, y: int, max: int|null, tab: int|null, entries: list<array{entry: int, spell: int|null, max: int|null}>, edges: list<array{to: int, type: string}>}>}  $layout
     * @param  list<int>  $shown  the tab ids the build shows, in the order the game lists them
     * @return list<array{tab: int, nodes: list<array{id: int, col: int, row: int, spells: list<int|null>, max: int, to: list<int>}>}>
     */
    public static function cells(array $layout, array $shown = []): array
    {
        $nodes = $layout['nodes'];
        $xs = [];
        foreach ($nodes as $node) {
            $xs[(int) $node['tab']][] = $node['x'];
        }
        $medianX = array_map(self::median(...), $xs);
        $medianY = self::median(array_column($nodes, 'y'));
        $at = [];
        foreach ($nodes as $id => $node) {
            $at[$id] = [self::readBack($node['x'], $medianX[(int) $node['tab']]), self::readBack($node['y'], $medianY)];
        }
        $top = $at === [] ? 0 : min(array_column($at, 1));
        $left = [];
        foreach ($nodes as $id => $node) {
            $tab = (int) $node['tab'];
            $left[$tab] = min($left[$tab] ?? PHP_INT_MAX, $at[$id][0]);
        }

        $ids = array_map('strval', array_keys($nodes));
        sort($ids, SORT_STRING);
        $trees = [];
        foreach ($ids as $key) {
            $id = (int) $key;
            $node = $nodes[$id];
            $tab = $node['tab'];
            if ($node['entries'] === [] || $tab === null || ($shown !== [] && ! in_array($tab, $shown, true))) {
                continue;
            }
            $to = [];
            foreach ($node['edges'] as $edge) {
                if (($nodes[$edge['to']]['tab'] ?? null) === $tab && ($to === [] || $to[array_key_last($to)] !== $edge['to'])) {
                    $to[] = $edge['to'];
                }
            }
            $trees[$tab][] = [
                'id' => $id,
                'col' => self::cell($at[$id][0], $left[$tab], self::COLS),
                'row' => self::cell($at[$id][1], $top, self::ROWS),
                'spells' => array_map(fn (array $entry): ?int => $entry['spell'], $node['entries']),
                'max' => max($node['entries'][0]['max'] ?? 0, $node['max'] ?? 0),
                'to' => $to,
            ];
        }

        $order = TalentTabOrder::of($shown !== [] ? $shown : array_keys($trees), $layout);
        $tabs = [...array_filter($order, fn (int $tab): bool => isset($trees[$tab])), ...array_diff(array_keys($trees), $order)];
        $out = [];
        foreach ($tabs as $tab) {
            $placed = $trees[$tab];
            usort($placed, fn (array $a, array $b): int => [$a['row'], $a['col'], (string) $a['id']] <=> [$b['row'], $b['col'], (string) $b['id']]);
            $out[] = ['tab' => $tab, 'nodes' => $placed];
        }

        return $out;
    }

    /**
     * The pixels of one tree: the frame's size, each node's slot and an arrow for every pair of joined nodes, drawn once,
     * pointing down the tree (rightward within a row).
     *
     * @param  list<array{id: int, col: int, row: int, to: list<int>}>  $nodes
     * @return array{width: int, height: int, slots: array<int, array{left: int, top: int}>, arrows: list<array{from: int, to: int, line: array{x1: float, y1: float, x2: float, y2: float}, head: string}>}
     */
    public static function frame(array $nodes): array
    {
        $width = max(self::COLS, ...array_map(fn (array $node): int => $node['col'] + 1, $nodes));
        $height = max(self::ROWS, ...array_map(fn (array $node): int => $node['row'] + 1, $nodes));
        $byId = [];
        $slots = [];
        foreach ($nodes as $node) {
            $byId[$node['id']] = $node;
            $slots[$node['id']] = ['left' => $node['col'] * self::PITCH, 'top' => $node['row'] * self::PITCH];
        }

        $pairs = [];
        foreach ($nodes as $node) {
            foreach ($node['to'] as $to) {
                if (! isset($byId[$to])) {
                    continue;
                }
                $a = $node;
                $b = $byId[$to];
                [$upper, $lower] = ([$a['row'] - $b['row'], $a['col'] - $b['col']] <=> [0, 0]) <= 0 ? [$a, $b] : [$b, $a];
                $pairs[$upper['id'].'>'.$lower['id']] = [$upper, $lower];
            }
        }
        $arrows = [];
        foreach ($pairs as [$upper, $lower]) {
            $arrow = self::arrow($upper, $lower);
            if ($arrow !== null) {
                $arrows[] = $arrow;
            }
        }

        return ['width' => $width * self::PITCH - self::GAP, 'height' => $height * self::PITCH - self::GAP, 'slots' => $slots, 'arrows' => $arrows];
    }

    /**
     * @param  array{id: int, col: int, row: int}  $from
     * @param  array{id: int, col: int, row: int}  $to
     * @return array{from: int, to: int, line: array{x1: float, y1: float, x2: float, y2: float}, head: string}|null
     */
    private static function arrow(array $from, array $to): ?array
    {
        $half = self::SLOT / 2;
        $ax = $from['col'] * self::PITCH + $half;
        $ay = $from['row'] * self::PITCH + $half;
        $dx = $to['col'] * self::PITCH + $half - $ax;
        $dy = $to['row'] * self::PITCH + $half - $ay;
        $reach = max(abs($dx), abs($dy));
        if ($reach === 0) {
            return null;
        }
        $trim = ($half + 3) / $reach;
        $x1 = $ax + $dx * $trim;
        $y1 = $ay + $dy * $trim;
        $x2 = $ax + $dx - $dx * $trim;
        $y2 = $ay + $dy - $dy * $trim;
        $length = hypot($x2 - $x1, $y2 - $y1);
        $ux = ($x2 - $x1) / $length;
        $uy = ($y2 - $y1) / $length;
        $hx = $x2 - $ux * self::HEAD;
        $hy = $y2 - $uy * self::HEAD;
        $wing = self::HEAD * 0.55;
        $head = [[$x2, $y2], [$hx - $uy * $wing, $hy + $ux * $wing], [$hx + $uy * $wing, $hy - $ux * $wing]];

        return [
            'from' => $from['id'],
            'to' => $to['id'],
            'line' => ['x1' => round($x1, 2), 'y1' => round($y1, 2), 'x2' => round($hx, 2), 'y2' => round($hy, 2)],
            'head' => implode(' ', array_map(fn (array $point): string => round($point[0], 2).','.round($point[1], 2), $head)),
        ];
    }

    /** @param  list<int>  $values */
    private static function median(array $values): int
    {
        sort($values);

        return $values[intdiv(count($values), 2)] ?? 0;
    }

    private static function readBack(int $value, int $median): int
    {
        return $median > 0 && $value > self::TYPO_FACTOR * $median ? intdiv($value, 10) : $value;
    }

    private static function cell(int $value, int $origin, int $last): int
    {
        return min((int) max(0.0, round(($value - $origin) / self::CELL)), $last - 1);
    }
}
