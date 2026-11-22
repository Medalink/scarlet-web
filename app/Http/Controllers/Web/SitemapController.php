<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\SitemapIndex;
use App\Actions\Public\SitemapUrls;
use App\Http\Controllers\Controller;
use App\Support\SearchKind;
use Illuminate\Http\Response;

/** The sitemap: an index of files, one for the fixed pages and one or more per search kind. Public. */
class SitemapController extends Controller
{
    public function index(SitemapIndex $index): Response
    {
        return $this->xml('sitemap.index', ['paths' => $index->handle()]);
    }

    public function pages(SitemapUrls $urls): Response
    {
        return $this->xml('sitemap.urls', ['rows' => $urls->handle(null, 1)]);
    }

    public function kind(SearchKind $kind, SitemapUrls $urls, int $page): Response
    {
        $rows = $urls->handle($kind, $page);
        abort_if($rows === null, 404);

        return $this->xml('sitemap.urls', ['rows' => $rows]);
    }

    /** @param  array<string, mixed>  $data */
    private function xml(string $view, array $data): Response
    {
        return response()->view($view, ['origin' => rtrim((string) config('app.url'), '/')] + $data)
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }
}
