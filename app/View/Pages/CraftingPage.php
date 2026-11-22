<?php

namespace App\View\Pages;

use App\Support\Board;
use App\Support\ItemIcons;
use App\Support\Profession;
use App\View\Format;

/** The data of docs/comps/cooking.html from the answer of App\Actions\Public\CraftingDashboard, in the shape of ProfessionPage. */
final class CraftingPage
{
    /** The width of the longest bar in the recipes table, in rem. */
    private const float BAR_REM = 5.6;

    /** The game's names for how hard a recipe was when it was made, by the `difficulty` of the answer. */
    private const array DIFFICULTY = ['Optimal', 'Medium', 'Easy', 'Trivial'];

    /** The modules of a row in the page's order, the spans a row of two takes and the span a row of one takes. */
    private const array ROWS = [
        [['recipes'], [], 'w12'],
        [['difficulty', 'points', 'levels'], ['w7', 'w5'], 'w12'],
        [['when', 'sitting'], ['w8', null], 'w12'],
        [['reagents', 'zones'], ['w7', 'w5'], 'w12'],
        [['board'], [], 'w12'],
    ];

    /**
     * @param  array<string, mixed>  $r  the answer of CraftingDashboard
     * @return array<string, mixed>
     */
    public static function data(Profession $profession, array $r): array
    {
        $own = ProfessionPage::own($r);
        $w = [...self::words($profession), 'players' => $own ? 'you' : 'players'];
        $t = StatsPage::totals($r['totals']);
        $few = $t['characters'] === null;
        $hours = StatsPage::rows($r['hours']);
        $levels = ProfessionCharts::byLevel(StatsPage::rows($r['by_level']));
        $ranked = Board::ofCrafting($profession);
        $arranged = ProfessionPage::arrange(self::ROWS, $few ? [] : [
            'recipes' => self::recipes(StatsPage::rows($r['recipes']), $t),
            'difficulty' => self::difficulty(StatsPage::rows($r['difficulty'])),
            'points' => ProfessionCharts::perPoint(StatsPage::rows($r['per_point']), 'crafts', $w['results']),
            'levels' => $levels === null ? null : ['spec' => $levels],
            'when' => $hours === [] ? null : ProfessionPage::week($hours, 'crafts', $w['attempt'], $w['attempts']),
            'sitting' => self::sitting($t),
            'reagents' => self::reagents(StatsPage::rows($r['reagents'])),
            'zones' => ProfessionPage::zones(StatsPage::rows($r['zones']), 'crafts', $w['result'], $w['results']),
            'board' => $ranked === null ? null : ProfessionPage::board($ranked, StatsPage::rows($r['board']), $w['results']),
        ]);

        return [
            'title' => ($own ? 'Your ' : '').$w['title'].': recipes, materials and places in WoW Forever',
            'own' => $own,
            'words' => $w,
            'facts' => self::facts($t, $few, $own),
            'callouts' => CraftingCallouts::for($r),
            'figures' => self::figures($t, $few, $own),
            'modules' => $arranged['modules'],
            'heads' => self::heads($w, $own),
            'layout' => $arranged['layout'],
            'dots' => null,
            'hoursSummary' => ProfessionPage::hoursSummary($hours, 'crafts'),
            'note' => $few ? ProfessionPage::few($w['attempts'], $own) : null,
        ];
    }

    /** @return array<string, string> */
    private static function words(Profession $profession): array
    {
        return [
            'title' => $profession->value,
            'description' => 'What players make with '.$profession->value.' in WoW Forever, the recipes they craft most, the materials those crafts use, when and where they craft and the characters with the most crafts.',
            'attempt' => 'craft', 'attempts' => 'crafts', 'result' => 'craft', 'results' => 'crafts', 'doing' => 'craft',
        ];
    }

    /**
     * @param  array<string, string>  $w
     * @return array<string, string>
     */
    private static function heads(array $w, bool $own): array
    {
        return [
            'recipes' => 'What gets made',
            'difficulty' => 'How hard the recipe was when it was made',
            'points' => ucfirst($w['results']).' for a skill point, by skill',
            'levels' => $w['title'].' skill, by character level',
            'when' => 'When '.$w['players'].' '.$w['doing'],
            'sitting' => 'A sitting',
            'reagents' => 'What the crafts used',
            'zones' => 'Zones with the most '.$w['results'],
            'board' => $own ? 'Your characters by '.$w['results'] : 'Most '.$w['results'],
        ];
    }

    /**
     * @param  array<string, int|float|null>  $t
     * @return list<string>
     */
    private static function facts(array $t, bool $few, bool $own): array
    {
        if ($few) {
            return [ProfessionPage::withoutNumbers($t, $own)];
        }

        $facts = [Format::count((int) $t['crafts'], 'craft'), ProfessionPage::by($t, $own)];
        if ((int) $t['made'] > 0) {
            $facts[] = Format::count((int) $t['made'], 'item').' made';
        }

        return $facts;
    }

    /**
     * @param  array<string, int|float|null>  $t
     * @return list<array{label: string, value: string, icon: string}>
     */
    private static function figures(array $t, bool $few, bool $own): array
    {
        $row = fn (string $label, ?string $value, string $icon): array => ProfessionPage::figure($few, $label, $value, $icon);

        return [
            $row('Crafts that give a skill point', CampsPage::share(min((int) $t['skill_ups'], (int) $t['crafts']), (int) $t['crafts']), 'shield'),
            $row('Recipes crafted', (int) $t['recipes'] > 0 ? Format::number((int) $t['recipes']) : null, 'item'),
            $row('Items made', (int) $t['made'] > 0 ? Format::number((int) $t['made']) : null, 'tack'),
            ...($own ? [] : [$row('Characters who craft', CampsPage::share((int) $t['characters'], (int) $t['scope_characters']), 'characters')]),
            $row('Median skill', $t['median_skill'] === null ? null : Format::number((float) $t['median_skill']), 'skills'),
            $row('Characters at '.Profession::SKILL_CAP, $t['at_cap'] === null ? null : Format::number((int) $t['at_cap']), 'crown'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $recipes
     * @param  array<string, int|float|null>  $t
     * @return array{rows: list<array<string, mixed>>, icons: bool, note: string|null}|null
     */
    private static function recipes(array $recipes, array $t): ?array
    {
        if ($recipes === []) {
            return null;
        }
        $top = max([1, ...array_column($recipes, 'crafts')]);
        $different = (int) $t['recipes'];
        $icons = ItemIcons::icons(array_map(fn (array $recipe): int => (int) $recipe['item_id'], array_filter($recipes, fn (array $recipe): bool => $recipe['item_id'] !== null)));

        return [
            'icons' => array_filter($icons) !== [],
            'rows' => array_map(fn (array $recipe): array => [
                'label' => (string) ($recipe['name'] ?? 'Recipe '.$recipe['recipe_id']),
                'href' => '/spells/'.$recipe['recipe_id'],
                'makes' => $recipe['item_id'] === null ? null : (string) ($recipe['item'] ?? 'Item '.$recipe['item_id']),
                'makesHref' => $recipe['item_id'] === null ? null : '/items/'.$recipe['item_id'],
                'makesIcon' => $recipe['item_id'] === null ? null : $icons[(int) $recipe['item_id']],
                'crafts' => Format::number((int) $recipe['crafts']),
                'bar' => round((int) $recipe['crafts'] / $top * self::BAR_REM, 2).'rem',
                'made' => Format::number((int) $recipe['made']),
                'characters' => $recipe['characters'] === null ? '–' : Format::number((int) $recipe['characters']),
                'skill' => $recipe['median_skill'] === null ? '–' : Format::number((float) $recipe['median_skill']),
                'uses' => implode(', ', array_map(
                    fn (array $reagent): string => (string) ($reagent['name'] ?? 'Item '.$reagent['item_id']).((int) $reagent['quantity'] > 1 ? ' ×'.$reagent['quantity'] : ''),
                    $recipe['reagents'],
                )),
            ], $recipes),
            'note' => $different > count($recipes) ? 'The '.count($recipes).' recipes with the most crafts, of '.Format::number($different).' crafted.' : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{rows: list<array{label: string, crafts: string, share: string}>}|null
     */
    private static function difficulty(array $rows): ?array
    {
        $rows = array_values(array_filter($rows, fn (array $row): bool => isset(self::DIFFICULTY[(int) $row['difficulty']])));
        if ($rows === []) {
            return null;
        }

        return ['rows' => array_map(fn (array $row): array => [
            'label' => self::DIFFICULTY[(int) $row['difficulty']],
            'crafts' => Format::number((int) $row['crafts']),
            'share' => CampsPage::share(min((int) $row['skill_ups'], (int) $row['crafts']), (int) $row['crafts']) ?? '–',
        ], $rows)];
    }

    /**
     * The sittings of all time and the medians of the last 90 days.
     *
     * @param  array<string, int|float|null>  $t
     * @return array{length: string, crafts: string, sittings: string, gap: string}|null
     */
    private static function sitting(array $t): ?array
    {
        if ((int) $t['sittings'] === 0) {
            return null;
        }

        return [
            'length' => $t['median_sitting_seconds'] === null ? '–' : CampsPage::visitLength((float) $t['median_sitting_seconds']),
            'crafts' => $t['median_sitting_crafts'] === null ? '–' : Format::number(round((float) $t['median_sitting_crafts'])),
            'sittings' => Format::number((int) $t['sittings']),
            'gap' => $t['median_gap'] === null ? '–' : Format::number((float) $t['median_gap'], 1),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>|null
     */
    private static function reagents(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }
        $top = max([1, ...array_column($rows, 'quantity')]);
        $icons = ItemIcons::icons(array_map(fn (array $row): int => (int) $row['item_id'], $rows));

        return array_map(fn (array $row, int $i): array => [
            'icon' => $icons[(int) $row['item_id']],
            'label' => (string) ($row['name'] ?? 'Item '.$row['item_id']),
            'value' => $i === 0 ? Format::number((int) $row['quantity']).' used' : Format::number((int) $row['quantity']),
            'share' => (int) $row['quantity'] / $top * 100,
            'href' => '/items/'.$row['item_id'],
        ], $rows, array_keys($rows));
    }
}
