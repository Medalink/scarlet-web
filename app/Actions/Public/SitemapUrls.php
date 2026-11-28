<?php

namespace App\Actions\Public;

use App\Support\Board;
use App\Support\Feat;
use App\Support\Profession;
use App\Support\SearchKind;
use App\Support\Server;
use App\Support\SitemapSource;

/** The entries of one sitemap file: the fixed pages, or a page of one search kind. */
class SitemapUrls
{
    public const int PER_FILE = 25000;

    public function __construct(private readonly SitemapSource $source, private readonly ResolveScope $scopes, private readonly ListRulesets $rulesets, private readonly TalentSpecs $specs) {}

    /**
     * The file's entries, or null when the kind has no rows on this page.
     *
     * @return list<array{path: string, modified: string|null}>|null
     */
    public function handle(?SearchKind $kind, int $page): ?array
    {
        if ($kind === null) {
            return $this->pages();
        }
        $rows = $this->source->page($kind, $page, self::PER_FILE);

        return $rows === [] ? null : $rows;
    }

    /**
     * The pages file: the fixed pages, then a ruleset, board and talent spec page for each one that exists.
     *
     * @return list<array{path: string, modified: string|null}>
     */
    private function pages(): array
    {
        $paths = ['/', '/stats', '/map', '/loot', '/loot/groups', '/camps', '/professions'];
        foreach (Profession::withPages() as $profession) {
            $paths[] = '/professions/'.$profession->slug();
        }
        array_push($paths, '/search', '/developers', '/download', '/leaderboards', '/feats', '/talents', '/stats/compare');
        foreach (Feat::cases() as $feat) {
            $paths[] = '/feats/'.$feat->value;
        }
        foreach ($this->rulesets->handle() as $ruleset) {
            $paths[] = '/stats/'.$ruleset['slug'];
        }
        foreach (Board::cases() as $board) {
            $paths[] = '/leaderboards/'.$board->value;
        }
        $scope = $this->scopes->handle(null, Server::All);
        foreach ($scope === null ? [] : $this->specs->handle($scope) as $spec) {
            $paths[] = '/talents/'.$spec['class_slug'].'/'.$spec['slug'];
        }

        return array_map(fn (string $path): array => ['path' => $path, 'modified' => null], array_values(array_unique($paths)));
    }
}
