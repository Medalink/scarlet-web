<?php

namespace App\View\Design;

use App\Support\Scene;

/** The head of a profession page with invented facts, to check a scene in the real app before its page exists. */
final class SceneHead
{
    /**
     * @return array{title: string, label: string, facts: list<string>, credit: string, callouts: list<array{key: string, title: string, text: string, share: string}>}
     */
    private static function crafting(string $title, string $label, string $things, string $recipe, string $material): array
    {
        return [
            'title' => $title,
            'label' => $label,
            'facts' => ['840,300 crafts', '3,210 characters', '412 recipes'],
            'credit' => $things.' models, textures and effects © Blizzard Entertainment, read from the WoW Forever client. The ground and the light are drawn by this page.',
            'callouts' => [
                ['key' => 'station', 'title' => $recipe, 'text' => 'The most made recipe', 'share' => '22% of crafts'],
                ['key' => 'work', 'title' => 'A sitting', 'text' => '6 crafts in a row', 'share' => '4 seconds apart'],
                ['key' => 'stock', 'title' => $material, 'text' => 'The most used material', 'share' => '31% of materials'],
            ],
        ];
    }

    /**
     * @return array{title: string, label: string, facts: list<string>, credit: string, callouts: list<array{key: string, title: string, text: string, share: string}>}
     */
    public static function data(Scene $scene): array
    {
        return match ($scene) {
            Scene::Fishing => [
                'title' => 'Fishing',
                'label' => "A fishing spot at night, drawn from the game's own models: a bobber on the water beside a school of fish, with a tackle box, a pole and a rack of fish on the bank",
                'facts' => ['1,204,880 casts', '4,310 characters', '6,120 hours at the water'],
                'credit' => 'Bobber, fish school, tackle box, pole and fish rack models, textures, ripples and splashes © Blizzard Entertainment, read from the WoW Forever client. The water, the bank and the moonlight are drawn by this page.',
                'callouts' => [
                    ['key' => 'bobber', 'title' => 'Bobber', 'text' => 'The wait for a bite is 15 seconds', 'share' => '82% of casts catch'],
                    ['key' => 'rack', 'title' => 'Raw Spotted Yellowtail', 'text' => 'The most caught fish', 'share' => '43% of catches'],
                    ['key' => 'box', 'title' => 'Clams, crates and trunks', 'text' => 'Catches that open for more', 'share' => '1 catch in 13'],
                ],
            ],
            Scene::Herbalism => [
                'title' => 'Herbalism',
                'label' => "A meadow at first light, drawn from the game's own models: Peacebloom, Silverleaf, Mageroyal, Kingsblood and other herbs growing where a player would pick them",
                'facts' => ['2,310,400 picks', '5,120 characters', '3,840 hours picking'],
                'credit' => 'Herb models and textures © Blizzard Entertainment, read from the WoW Forever client. The ground and the light are drawn by this page.',
                'callouts' => [
                    ['key' => 'peacebloom', 'title' => 'Peacebloom', 'text' => 'The most picked herb', 'share' => '18% of picks'],
                    ['key' => 'mageroyal', 'title' => 'Mageroyal', 'text' => 'A skill point every 2.4 picks', 'share' => 'Skill 50 to 100'],
                    ['key' => 'silverleaf', 'title' => 'Silverleaf', 'text' => 'Picked most in one zone', 'share' => '41% of its picks'],
                ],
            ],
            Scene::Mining => [
                'title' => 'Mining',
                'label' => "A mine floor lit by a forge, drawn from the game's own models: copper, tin, iron and mithril veins, a smelting forge, a mine cart, a lantern and a mining pick",
                'facts' => ['1,870,200 strikes', '4,460 characters', '2,950 hours at the vein'],
                'credit' => 'Vein, forge, mine cart, lantern and mining pick models, textures and sparkles © Blizzard Entertainment, read from the WoW Forever client. The ground and the light are drawn by this page.',
                'callouts' => [
                    ['key' => 'copper', 'title' => 'Copper Vein', 'text' => 'The most struck vein', 'share' => '34% of strikes'],
                    ['key' => 'mithril', 'title' => 'Mithril Deposit', 'text' => 'A vein gives 2.6 strikes', 'share' => '38% are first strikes'],
                    ['key' => 'forge', 'title' => 'Forge', 'text' => 'Ore smelted into bars', 'share' => '61% of ore'],
                ],
            ],
            Scene::Skinning => [
                'title' => 'Skinning',
                'label' => "A skinner's corner at dusk, drawn from the game's own models: two tanning racks, stacks of hides, a leather bag and a lantern",
                'facts' => ['1,402,900 skins', '3,980 characters', '1,120 hours skinning'],
                'credit' => 'Tanning rack, hide, leather bag and lantern models and textures © Blizzard Entertainment, read from the WoW Forever client. The ground and the light are drawn by this page.',
                'callouts' => [
                    ['key' => 'rack', 'title' => 'Light Leather', 'text' => 'The most skinned leather', 'share' => '46% of skins'],
                    ['key' => 'hides', 'title' => 'Hides', 'text' => 'A hide comes off rarely', 'share' => '1 skin in 21'],
                    ['key' => 'bag', 'title' => 'Ruined Leather Scraps', 'text' => 'What a low skill gets', 'share' => '9% of skins'],
                ],
            ],
            Scene::Alchemy => self::crafting('Alchemy', "An alchemist's corner, drawn from the game's own models: two alchemy sets, a cauldron over a fire, and a table with a bubbling bowl and bottles", 'Alchemy set, cauldron, table, bowl, bottle and book', 'Minor Healing Potion', 'Peacebloom'),
            Scene::Blacksmithing => self::crafting('Blacksmithing', "A smithy, drawn from the game's own models: an anvil with a hammer on it, a forge, a furnace, a grindstone, a weapon rack and a bench of metal bars", 'Anvil, hammer, forge, furnace, grindstone, weapon rack, bench and metal bar', 'Rough Sharpening Stone', 'Copper Bar'),
            Scene::Enchanting => self::crafting('Enchanting', "An enchanter's study, drawn from the game's own models: a lectern, a column of light, stacks of books, candelabras and candles", 'Lectern, light, book, candelabra and candle', 'Enchant Bracer - Minor Health', 'Strange Dust'),
            Scene::Engineering => self::crafting('Engineering', "An engineer's workshop, drawn from the game's own models: an autolathe, two gnomish machines, a toolbox, a wrench, a powder keg, dynamite and a mortar", 'Autolathe, machine, toolbox, wrench, powder keg, dynamite and mortar', 'Rough Blasting Powder', 'Rough Stone'),
            Scene::Leatherworking => self::crafting('Leatherworking', "A leatherworker's yard, drawn from the game's own models: a tanning rack, a bench with boots on it, hides, two saddles, a leather bag and a lantern", 'Tanning rack, bench, boot, hide, saddle, leather bag and lantern', 'Light Armor Kit', 'Light Leather'),
            Scene::Tailoring => self::crafting('Tailoring', "A tailor's room, drawn from the game's own models: a loom, a spinning wheel, a table of cloth and thread, and bolts of cloth", 'Loom, spinning wheel, table, bolt of cloth and spool', 'Bolt of Linen Cloth', 'Linen Cloth'),
            Scene::Cooking => self::crafting('Cooking', "A cook's fire, drawn from the game's own models: a cooking pot over a fire, a boar on a spit, a rack of smoked meat, a table of food and a sack", 'Cooking pot, spit, smoked meat rack, table, food and sack', 'Spotted Yellowtail', 'Raw Spotted Yellowtail'),
            Scene::FirstAid => self::crafting('First Aid', "A field hospital, drawn from the game's own models: a tent, a bed, first aid crates and a lantern", 'Tent, bed, first aid crate and lantern', 'Linen Bandage', 'Linen Cloth'),
        };
    }
}
