<?php

namespace App\Support;

use App\View\Format;

/**
 * The site's own pages that search finds: what each is called, where it is, what it is and the words people use for
 * it. The pages that exist whatever the data holds are listed here; the rulesets and the talent specs come from the
 * Actions that list them for their pages, so a path listed here always answers.
 */
final class SitePages
{
    /** How long the specs that have characters stay in the cache. */
    public const int SPECS_SECONDS = 600;

    public static function specsKey(): string
    {
        return 'site-pages:specs';
    }

    /**
     * @param  list<array{slug: string, label: string, recorded: bool}>  $rulesets
     * @param  list<array{class: string, class_slug: string, name: string, slug: string, characters: int}>  $specs
     * @return list<array{title: string, path: string, meta: string, keywords: list<string>}>
     */
    public static function all(array $rulesets, array $specs): array
    {
        $pages = [
            self::page('Stats', '/stats', 'Dashboard of the recorded play of every ruleset', ['statistics', 'overview', 'numbers']),
            self::page('World map', '/map', 'Where characters walked, died and met creatures', ['map', 'heat', 'heatmap', 'deaths', 'paths']),
            self::page('Loot', '/loot', 'What creatures, objects and containers gave and how often each item dropped', ['loot', 'drops', 'drop rate', 'drop rates', 'loot table']),
            self::page('Loot rules and group size', '/loot/groups', 'What group loot, free for all and group size change in what a corpse holds and who keeps it', ['loot rules', 'group loot', 'free for all', 'need before greed', 'round robin', 'rolls', 'group size']),
            self::page('Camps', '/camps', 'How players use the campsite: visits, Camp Benefits and what gets set up', ['camp', 'campfire', 'tent', 'chair', 'rested', 'benefits']),
            ...array_map(self::profession(...), Profession::withPages()),
            self::page('Rulesets', '/stats/compare', 'The rulesets side by side', ['compare', 'comparison']),
        ];
        foreach ($rulesets as $ruleset) {
            if ($ruleset['recorded']) {
                $pages[] = self::page($ruleset['label'], '/stats/'.$ruleset['slug'], 'Dashboard of one ruleset', []);
            }
        }
        $pages[] = self::page('Feats', '/feats', 'Every feat of five tiers and the share of characters that hold each tier', ['feat', 'achievement', 'achievements', 'tier', 'copper', 'silver', 'gold', 'legend']);
        $pages[] = self::page('Leaderboards', '/leaderboards', 'The boards of public characters', ['board', 'ranking', 'rankings', 'top', 'best']);
        foreach (Board::cases() as $board) {
            $pages[] = self::page($board->title(), '/leaderboards/'.$board->value, 'Leaderboard of public characters', []);
        }
        $pages[] = self::page('Talents', '/talents', 'The specs characters play', ['talent', 'specs', 'builds']);
        $listed = [];
        foreach ($specs as $spec) {
            $path = '/talents/'.$spec['class_slug'].'/'.$spec['slug'];
            if (isset($listed[$path])) {
                continue;
            }
            $listed[$path] = true;
            $class = Format::className($spec['class']);
            $pages[] = self::page($spec['name'].' '.$class, $path, 'Talent picks of '.$spec['name'].' '.$class.' characters', []);
        }
        $pages[] = self::page('Download', '/download', 'The Scarlet app and the addon', ['app', 'install', 'installer', 'addon', 'windows']);
        $pages[] = self::page('API reference', '/developers', 'The API: how to get a key, every path, its parameters and its answer', ['api', 'developers', 'developer', 'endpoints', 'json', 'documentation']);

        return $pages;
    }

    /** @return array{title: string, path: string, meta: string, keywords: list<string>} */
    private static function profession(Profession $profession): array
    {
        [$meta, $keywords] = match ($profession) {
            Profession::Fishing => ['What players catch, how long a bite takes and where they fish', ['fish', 'fishing', 'catch', 'bobber']],
            Profession::Herbalism => ['What players pick, how long a pick takes and where they pick herbs', ['herb', 'herbs', 'herbalism', 'pick']],
            Profession::Mining => ['What players mine, how many strikes a vein takes and where they mine', ['ore', 'mine', 'mining', 'vein']],
            Profession::Skinning => ['What players skin, which creatures give the most and where they skin', ['skin', 'skinning', 'leather', 'hide']],
            Profession::Alchemy => ['What players brew, which recipes they craft most and what the crafts use', ['alchemy', 'potion', 'elixir', 'flask', 'craft']],
            Profession::Blacksmithing => ['What players forge, which recipes they craft most and what the crafts use', ['blacksmithing', 'smith', 'forge', 'anvil', 'craft']],
            Profession::Enchanting => ['What players enchant, which recipes they craft most and what the crafts use', ['enchanting', 'enchant', 'disenchant', 'craft']],
            Profession::Engineering => ['What players build, which recipes they craft most and what the crafts use', ['engineering', 'gadget', 'gnomish', 'goblin', 'craft']],
            Profession::Leatherworking => ['What players sew from leather, which recipes they craft most and what the crafts use', ['leatherworking', 'leather', 'armor', 'craft']],
            Profession::Tailoring => ['What players sew from cloth, which recipes they craft most and what the crafts use', ['tailoring', 'cloth', 'bag', 'craft']],
            Profession::Cooking => ['What players cook, which recipes they craft most and what the crafts use', ['cooking', 'cook', 'food', 'recipe', 'craft']],
            Profession::FirstAid => ['What players make with First Aid, which recipes they craft most and what the crafts use', ['first aid', 'bandage', 'craft']],
            Profession::Lockpicking, Profession::Poisons => ['', []],
        };

        return self::page($profession->value, '/professions/'.$profession->slug(), $meta, [...$keywords, 'profession']);
    }

    /**
     * @param  list<string>  $keywords
     * @return array{title: string, path: string, meta: string, keywords: list<string>}
     */
    private static function page(string $title, string $path, string $meta, array $keywords): array
    {
        return ['title' => $title, 'path' => $path, 'meta' => $meta, 'keywords' => $keywords];
    }
}
