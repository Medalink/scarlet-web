<?php

namespace App\Actions\Public;

use App\Support\SearchKind;
use App\Support\SitemapSource;

/** The sitemap files the sitemap index lists. */
class SitemapIndex
{
    public function __construct(private readonly SitemapSource $source) {}

    /**
     * The path of every sitemap file: the pages file, then one file per page of each kind that has rows.
     *
     * @return list<string>
     */
    public function handle(): array
    {
        $paths = ['/sitemaps/pages.xml'];
        foreach (SearchKind::cases() as $kind) {
            $files = (int) ceil($this->source->count($kind) / SitemapUrls::PER_FILE);
            for ($page = 1; $page <= $files; $page++) {
                $paths[] = '/sitemaps/'.$kind->value.'-'.$page.'.xml';
            }
        }

        return $paths;
    }
}
