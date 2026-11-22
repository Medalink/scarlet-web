<?php

namespace App\View\Design;

use App\Support\Profession;
use App\Support\Scene;

/** The head of a profession page with invented facts, to check a scene in the real app before its page exists. */
final class SceneHead
{
    /**
     * @return array{title: string, label: string, facts: list<string>, credit: string, callouts: list<array{key: string, title: string, text: string, share: string}>}
     */
    public static function data(Scene $scene): array
    {
        $facts = match ($scene) {
            Scene::Fishing => ['1,204,880 casts', '4,310 characters', '6,120 hours at the water'],
            Scene::Herbalism => ['2,310,400 picks', '5,120 characters', '3,840 hours picking'],
            Scene::Mining => ['1,870,200 strikes', '4,460 characters', '2,950 hours at the vein'],
            Scene::Skinning => ['1,402,900 skins', '3,980 characters', '1,120 hours skinning'],
            Scene::Camp => ['12,480 visits', '3,120 characters', '4,030 hours by the fire'],
            default => ['840,300 crafts', '3,210 characters', '412 recipes'],
        };
        $callouts = match ($scene) {
            Scene::Fishing => [
                ['key' => 'bobber', 'title' => 'Bobber', 'text' => 'The wait for a bite is 15 seconds', 'share' => '82% of casts catch'],
                ['key' => 'rack', 'title' => 'Raw Spotted Yellowtail', 'text' => 'The most caught fish', 'share' => '43% of catches'],
                ['key' => 'box', 'title' => 'Clams, crates and trunks', 'text' => 'Catches that open for more', 'share' => '1 catch in 13'],
            ],
            Scene::Herbalism => [
                ['key' => 'peacebloom', 'title' => 'Peacebloom', 'text' => 'The most picked herb', 'share' => '18% of picks'],
                ['key' => 'mageroyal', 'title' => 'Mageroyal', 'text' => 'A skill point every 2.4 picks', 'share' => 'Skill 50 to 100'],
                ['key' => 'silverleaf', 'title' => 'Silverleaf', 'text' => 'Picked most in one zone', 'share' => '41% of its picks'],
            ],
            Scene::Mining => [
                ['key' => 'copper', 'title' => 'Copper Vein', 'text' => 'The most struck vein', 'share' => '34% of strikes'],
                ['key' => 'mithril', 'title' => 'Mithril Deposit', 'text' => 'A vein gives 2.6 strikes', 'share' => '38% are first strikes'],
                ['key' => 'forge', 'title' => 'Forge', 'text' => 'Ore smelted into bars', 'share' => '61% of ore'],
            ],
            Scene::Skinning => [
                ['key' => 'rack', 'title' => 'Light Leather', 'text' => 'The most skinned leather', 'share' => '46% of skins'],
                ['key' => 'hides', 'title' => 'Hides', 'text' => 'A hide comes off rarely', 'share' => '1 skin in 21'],
                ['key' => 'bag', 'title' => 'Ruined Leather Scraps', 'text' => 'What a low skill gets', 'share' => '9% of skins'],
            ],
            Scene::Camp => [
                ['key' => 'fire', 'title' => 'Campfire', 'text' => 'Sit 60 seconds for Camp Benefits', 'share' => '88% of visits sit down'],
                ['key' => 'tent', 'title' => 'Camp Tent', 'text' => 'Rest 30 seconds for rested experience, once an hour', 'share' => '61% of visits use a tent'],
                ['key' => 'chair', 'title' => 'Camp Chair', 'text' => 'Adds 2% critical strike to the benefits', 'share' => '44% of Camp Benefits come with a chair'],
            ],
            Scene::Alchemy => self::crafting('Minor Healing Potion', 'Peacebloom'),
            Scene::Blacksmithing => self::crafting('Rough Sharpening Stone', 'Copper Bar'),
            Scene::Enchanting => self::crafting('Enchant Bracer - Minor Health', 'Strange Dust'),
            Scene::Engineering => self::crafting('Rough Blasting Powder', 'Rough Stone'),
            Scene::Leatherworking => self::crafting('Light Armor Kit', 'Light Leather'),
            Scene::Tailoring => self::crafting('Bolt of Linen Cloth', 'Linen Cloth'),
            Scene::Cooking => self::crafting('Spotted Yellowtail', 'Raw Spotted Yellowtail'),
            Scene::FirstAid => self::crafting('Linen Bandage', 'Linen Cloth'),
        };

        return [
            'title' => $scene === Scene::Camp ? 'Camps' : Profession::fromSlug($scene->value)->value ?? '',
            'label' => $scene->label(),
            'facts' => $facts,
            'credit' => $scene->credit(),
            'callouts' => $callouts,
        ];
    }

    /** @return list<array{key: string, title: string, text: string, share: string}> */
    private static function crafting(string $recipe, string $material): array
    {
        return [
            ['key' => 'station', 'title' => $recipe, 'text' => 'The most made recipe', 'share' => '22% of crafts'],
            ['key' => 'work', 'title' => 'A sitting', 'text' => '6 crafts in a row', 'share' => '4 seconds apart'],
            ['key' => 'stock', 'title' => $material, 'text' => 'The most used material', 'share' => '31% of materials'],
        ];
    }
}
