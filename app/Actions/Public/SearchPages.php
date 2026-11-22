<?php

namespace App\Actions\Public;

use App\Support\PageScope;
use App\Support\SearchMatch;
use App\Support\Server;
use App\Support\SitePages;
use Illuminate\Support\Facades\Cache;

class SearchPages
{
    private const int FUZZY_MIN = 4;

    public function __construct(private readonly ListRulesets $rulesets, private readonly TalentSpecs $specs) {}

    /**
     * The site's own pages whose title or keywords match the text, best first: the tier of the match, then the order
     * of the registry. A keyword never ranks above `contains`, so a page found by its title comes first. A close
     * spelling finds pages only when nothing matched otherwise, the closest first.
     *
     * @return list<array{title: string, path: string, meta: string, match: string}>
     */
    public function handle(string $term): array
    {
        /** @var list<array{class: string, class_slug: string, tab_id: int, name: string, slug: string, characters: int}> $specs */
        $specs = Cache::remember(SitePages::specsKey(), SitePages::SPECS_SECONDS, fn (): array => $this->specs->handle(new PageScope(null, Server::All, null)));
        $pages = SitePages::all($this->rulesets->handle(), $specs);

        $found = $this->matching($pages, $term);
        if ($found === [] && mb_strlen(trim($term)) >= self::FUZZY_MIN) {
            $found = $this->close($pages, $term);
        }
        usort($found, fn (array $a, array $b): int => [$a['rank'], $a['distance'], $a['order']] <=> [$b['rank'], $b['distance'], $b['order']]);

        return array_map(fn (array $hit): array => ['title' => $hit['title'], 'path' => $hit['path'], 'meta' => $hit['meta'], 'match' => SearchMatch::fromRank($hit['rank'])->value], $found);
    }

    /**
     * @param  list<array{title: string, path: string, meta: string, keywords: list<string>}>  $pages
     * @return list<array{title: string, path: string, meta: string, rank: int, distance: int, order: int}>
     */
    private function matching(array $pages, string $term): array
    {
        $found = [];
        foreach ($pages as $order => $page) {
            $rank = SearchMatch::judge($page['title'], $term)?->rank();
            foreach ($page['keywords'] as $keyword) {
                $tier = SearchMatch::judge($keyword, $term);
                $kept = $tier === null ? null : max($tier->rank(), SearchMatch::Contains->rank());
                $rank = $kept === null ? $rank : min($rank ?? $kept, $kept);
            }
            if ($rank !== null) {
                $found[] = ['title' => $page['title'], 'path' => $page['path'], 'meta' => $page['meta'], 'rank' => $rank, 'distance' => 0, 'order' => $order];
            }
        }

        return $found;
    }

    /**
     * @param  list<array{title: string, path: string, meta: string, keywords: list<string>}>  $pages
     * @return list<array{title: string, path: string, meta: string, rank: int, distance: int, order: int}>
     */
    private function close(array $pages, string $term): array
    {
        $words = SearchMatch::words($term);
        $found = [];
        foreach ($pages as $order => $page) {
            $distance = $this->distance($words, SearchMatch::words(implode(' ', [$page['title'], ...$page['keywords']])));
            if ($distance !== null) {
                $found[] = ['title' => $page['title'], 'path' => $page['path'], 'meta' => $page['meta'], 'rank' => SearchMatch::Fuzzy->rank(), 'distance' => $distance, 'order' => $order];
            }
        }

        return $found;
    }

    /**
     * @param  list<string>  $words
     * @param  list<string>  $known
     */
    private function distance(array $words, array $known): ?int
    {
        $total = 0;
        foreach ($words as $word) {
            $allowed = intdiv(mb_strlen($word), 4);
            $best = null;
            foreach ($known as $candidate) {
                $edits = levenshtein($word, $candidate);
                $best = $best === null || $edits < $best ? $edits : $best;
            }
            if ($best === null || $best > $allowed) {
                return null;
            }
            $total += $best;
        }

        return $total;
    }
}
