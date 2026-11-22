<?php

namespace App\Support\ClassPage;

use App\Support\ItemIcons;
use App\Support\SpellIcons;
use UnitEnum;

/** Finds the addresses of every icon a page object or band holds, one batched read for the items and one for the spells. */
final class ClassIcons
{
    public static function of(ClassPage|ClassBand $page): IconMap
    {
        $found = [];
        self::collect($page, $found);
        $items = $spells = [];
        foreach ($found as $icon) {
            if ($icon->kind === IconKind::Item) {
                $items[] = $icon->id;

                continue;
            }
            $spells[] = $icon->id;
        }
        $itemUrls = ItemIcons::icons($items);
        $spellUrls = SpellIcons::icons($spells);
        $urls = [];
        foreach ($found as $key => $icon) {
            $urls[$key] = $icon->kind === IconKind::Item ? ($itemUrls[$icon->id] ?? null) : ($spellUrls[$icon->id] ?? null);
        }

        return new IconMap($urls);
    }

    /** @param  array<string, Icon>  $found */
    private static function collect(mixed $node, array &$found): void
    {
        if ($node instanceof Icon) {
            $found[IconMap::key($node)] = $node;

            return;
        }
        if (is_array($node) || (is_object($node) && ! $node instanceof UnitEnum)) {
            foreach (is_array($node) ? $node : get_object_vars($node) as $child) {
                self::collect($child, $found);
            }
        }
    }
}
