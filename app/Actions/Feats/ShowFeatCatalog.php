<?php

namespace App\Actions\Feats;

use App\Support\FeatCatalog;

/** The feat catalog with the share of characters at each tier of each feat or higher: `GET /api/v1/public/feats`. */
class ShowFeatCatalog
{
    public function __construct(
        private readonly ExportCatalog $catalog,
        private readonly ReadFeatShares $shares,
    ) {}

    /** @return array{catalog: int, feats: list<array<string, mixed>>} a feat's `shares` is null until 20 characters are counted for it */
    public function handle(): array
    {
        $shares = $this->shares->handle();
        $feats = [];
        foreach ($this->catalog->handle()['feats'] as $feat) {
            $feats[] = $feat + ['shares' => $shares[$feat['key']] ?? null];
        }

        return ['catalog' => FeatCatalog::VERSION, 'feats' => $feats];
    }
}
