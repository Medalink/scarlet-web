<?php

namespace App\Support;

/** The order a build lists its talent trees in, which the app's build view uses (scarlet-app companion/src/talents.rs, `build_of`). */
final class TalentTabOrder
{
    /**
     * The tabs by the leftmost position of their nodes in the layout, when every tab has a node with a spell entry in it;
     * otherwise in the order the game lists them. Tabs at the same position keep the game's order.
     *
     * @param  list<int>  $game  the tab ids in the order the build's `tab_points` lists them
     * @param  array{nodes: array<int, array{x: int, tab: int|null, entries: list<mixed>}>}|null  $layout
     * @return list<int>
     */
    public static function of(array $game, ?array $layout): array
    {
        $game = array_values(array_unique($game));
        $left = [];
        foreach ($layout['nodes'] ?? [] as $node) {
            if ($node['entries'] !== [] && $node['tab'] !== null) {
                $left[$node['tab']] = min($left[$node['tab']] ?? PHP_INT_MAX, $node['x']);
            }
        }
        if ($game === [] || array_diff($game, array_keys($left)) !== []) {
            return $game;
        }
        $place = array_flip($game);
        usort($game, fn (int $a, int $b): int => [$left[$a], $place[$a]] <=> [$left[$b], $place[$b]]);

        return $game;
    }
}
