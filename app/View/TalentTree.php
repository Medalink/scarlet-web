<?php

namespace App\View;

/**
 * The drawing of a talent tree from its layout (App\Actions\Public\TalentLayout): where each node sits, which nodes it
 * joins and what each node says. The client draws a node at its raw position divided by 10, with y downward, and so
 * does this. A node is sized from the closest pair of nodes, so a layout of any scale fits.
 */
final class TalentTree
{
    /** The share of the closest spacing a node's radius takes. */
    private const float FILL = 0.36;

    /** A node's text is this multiple of its radius, as the component's font-size repeats. */
    public const float TEXT = 0.9;

    /** The px the text renders at, at least: below it the tree scrolls inside its box instead of shrinking. */
    private const float MIN_TEXT_PX = 11.0;

    /**
     * @param  array{nodes: array<int, array{id: int, x: int, y: int, tab: int|null, edges: list<array{to: int, type: string}>}>}  $layout
     * @param  array<int, array{title: string, text?: string, class: string, on?: bool}>  $marks  by node id: its tooltip, the text in it, its state class and whether it counts for the edges it ends
     * @param  array<int, string>  $tabs  tab names by tab id; a tab with a name gets a heading over its nodes
     * @return array{box: string, r: float, minw: int, nodes: list<array{x: float, y: float, text: string, title: string, class: string}>, edges: list<array{x1: float, y1: float, x2: float, y2: float, on: bool}>, headings: list<array{x: float, y: float, text: string}>}
     */
    public static function geometry(array $layout, array $marks, array $tabs = []): array
    {
        $points = [];
        foreach ($layout['nodes'] as $id => $node) {
            $points[$id] = [$node['x'] / 10, -$node['y'] / 10];
        }
        if ($points === []) {
            return ['box' => '0 0 1 1', 'r' => 1.0, 'minw' => 0, 'nodes' => [], 'edges' => [], 'headings' => []];
        }
        $r = round(self::spacing($points) * self::FILL, 2);

        $nodes = [];
        foreach ($layout['nodes'] as $id => $node) {
            $mark = $marks[$id] ?? ['title' => '', 'class' => 'off'];
            $nodes[] = ['x' => $points[$id][0], 'y' => $points[$id][1], 'text' => $mark['text'] ?? '', 'title' => $mark['title'], 'class' => $mark['class']];
        }

        $edges = [];
        $seen = [];
        foreach ($layout['nodes'] as $id => $node) {
            foreach ($node['edges'] as $edge) {
                $to = $edge['to'];
                $key = min($id, $to).':'.max($id, $to);
                if (! isset($points[$to]) || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $edges[] = [
                    'x1' => $points[$id][0], 'y1' => $points[$id][1], 'x2' => $points[$to][0], 'y2' => $points[$to][1],
                    'on' => ($marks[$id]['on'] ?? false) && ($marks[$to]['on'] ?? false),
                ];
            }
        }

        $headings = [];
        foreach ($tabs as $tab => $name) {
            $of = array_filter($layout['nodes'], fn (array $node): bool => $node['tab'] === $tab);
            if ($of !== []) {
                $xs = array_map(fn (array $node): float => $points[$node['id']][0], $of);
                $ys = array_map(fn (array $node): float => $points[$node['id']][1], $of);
                $headings[] = ['x' => round((min($xs) + max($xs)) / 2, 2), 'y' => round(min($ys) - $r * 2.4, 2), 'text' => $name];
            }
        }

        $xs = array_column($points, 0);
        $ys = array_column($points, 1);
        $pad = $r * 2.2;
        $top = min([...$ys, ...array_column($headings, 'y')]) - ($headings === [] ? $pad : $r);
        $box = [min($xs) - $pad, $top, max($xs) - min($xs) + 2 * $pad, max($ys) + $pad - $top];

        return [
            'box' => implode(' ', array_map(fn (float $n): string => (string) round($n, 2), $box)),
            'r' => $r,
            'minw' => (int) ceil($box[2] * self::MIN_TEXT_PX / ($r * self::TEXT)),
            'nodes' => $nodes,
            'edges' => $edges,
            'headings' => $headings,
        ];
    }

    /**
     * What charts.js reads the tree with: one line per node, in node order, from its title.
     *
     * @param  array{box: string, nodes: list<array{title: string}>}  $tree
     * @return array{kind: string, w: float, h: float, out: string, summary: array{0: string, 1: string}, say: list<array{0: string, 1: string}>}
     */
    public static function chart(array $tree, string $id, string $label): array
    {
        $say = array_map(static function (array $node): array {
            $lead = strstr($node['title'], ', ', true);

            return $lead === false ? [$node['title'], ''] : [$lead, substr($node['title'], strlen($lead))];
        }, $tree['nodes']);
        [, , $w, $h] = array_map('floatval', explode(' ', $tree['box']));

        return ['kind' => 'tree', 'w' => $w, 'h' => $h, 'out' => $id.'-out', 'summary' => [$label, ''], 'say' => $say];
    }

    /**
     * The distance between the two closest nodes.
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    private static function spacing(array $points): float
    {
        $best = INF;
        $list = array_values($points);
        $count = count($list);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $distance = hypot($list[$i][0] - $list[$j][0], $list[$i][1] - $list[$j][1]);
                if ($distance > 0 && $distance < $best) {
                    $best = $distance;
                }
            }
        }

        return is_finite($best) ? $best : 40.0;
    }
}
