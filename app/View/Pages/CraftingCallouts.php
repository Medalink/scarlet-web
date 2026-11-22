<?php

namespace App\View\Pages;

use App\Actions\Rollups\CraftReagents;
use App\View\Format;

/** The three callouts on the station, work and stock props of a crafting scene, from the answer of App\Actions\Public\CraftingDashboard. */
final class CraftingCallouts
{
    /**
     * @param  array<string, mixed>  $r
     * @return list<array{key: string, title: string, text: string, share: string|null}>
     */
    public static function for(array $r): array
    {
        $t = StatsPage::totals($r['totals']);
        if ($t['characters'] === null) {
            return [];
        }
        $crafts = (int) $t['crafts'];
        $recipe = StatsPage::rows($r['recipes'])[0] ?? null;
        $sitting = $t['median_sitting_crafts'] === null ? null : Format::count(round((float) $t['median_sitting_crafts']), 'craft').' in a row';
        $apart = $t['median_gap'] === null ? null : ProfessionPage::seconds((float) $t['median_gap']).' apart';
        $reagents = StatsPage::rows($r['reagents']);
        $stock = $reagents === [] ? self::skillPoint($t, $crafts) : self::material($reagents);

        return ProfessionPage::callouts([
            ['station', $recipe === null ? null : (string) ($recipe['name'] ?? 'Recipe '.$recipe['recipe_id']), 'The most crafted recipe', $recipe === null ? null : ProfessionPage::shareOf($recipe['crafts'], $crafts, 'of crafts')],
            ['work', 'A sitting', $sitting, $apart],
            $stock,
        ]);
    }

    /**
     * The share of the most used material is of every material the crafts used, which the list of ten names only while it
     * is shorter than that.
     *
     * @param  list<array<string, mixed>>  $reagents
     * @return array{0: string, 1: string, 2: string, 3: string|null}
     */
    private static function material(array $reagents): array
    {
        $total = (int) array_sum(array_column($reagents, 'quantity'));
        $whole = count($reagents) < CraftReagents::LIMIT ? $total : 0;

        return ['stock', (string) ($reagents[0]['name'] ?? 'Item '.$reagents[0]['item_id']), 'The most used material', ProfessionPage::shareOf($reagents[0]['quantity'], $whole, 'of materials')];
    }

    /**
     * @param  array<string, int|float|null>  $t
     * @return array{0: string, 1: string, 2: string|null, 3: string|null}
     */
    private static function skillPoint(array $t, int $crafts): array
    {
        $skillUps = (int) $t['skill_ups'];
        $every = $skillUps > 0 ? max(1.0, $crafts / $skillUps) : null;
        $text = $every === null ? null : Format::number($every, $every < 10 ? 1 : 0);

        return [
            'stock',
            'A skill point',
            $text === null ? null : ($text === '1' || $text === '1.0' ? 'Every craft' : 'Every '.$text.' crafts'),
            (int) $t['trivial'] > 0 ? ProfessionPage::shareOf($t['trivial'], $crafts, 'of crafts are trivial') : null,
        ];
    }
}
