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
            self::page('Rulesets', '/rulesets', 'The rulesets side by side', ['compare', 'comparison']),
            self::page('Hardcore deaths', '/hardcore-deaths', 'Hardcore deaths by level and by day', ['hardcore', 'deaths', 'died', 'death']),
        ];
        foreach ($rulesets as $ruleset) {
            if ($ruleset['recorded']) {
                $pages[] = self::page($ruleset['label'], '/rulesets/'.$ruleset['slug'], 'Dashboard of one ruleset', []);
            }
        }
        $pages[] = self::page('Leaderboards', '/leaderboards', 'The boards of public characters', ['board', 'ranking', 'rankings', 'top', 'best']);
        foreach (Board::cases() as $board) {
            $pages[] = self::page($board->title(), '/leaderboards/'.$board->value, 'Leaderboard of public characters', []);
        }
        $pages[] = self::page('Talents', '/talents', 'The specs characters play', ['talent', 'specs', 'builds']);
        foreach ($specs as $spec) {
            $class = Format::className($spec['class']);
            $pages[] = self::page($spec['name'].' '.$class, '/talents/'.$spec['class_slug'].'/'.$spec['slug'], 'Talent picks of '.$spec['name'].' '.$class.' characters', []);
        }
        $pages[] = self::page('Download', '/download', 'The Scarlet app and the addon', ['app', 'install', 'installer', 'addon', 'windows']);
        $pages[] = self::page('API reference', '/developers', 'The public API: every path, its parameters and its answer', ['api', 'developers', 'developer', 'endpoints', 'json', 'documentation']);

        return $pages;
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
