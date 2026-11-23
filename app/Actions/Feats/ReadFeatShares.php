<?php

namespace App\Actions\Feats;

use Illuminate\Support\Facades\DB;

/** What `feat_shares` holds for some feats: the percent of characters at each tier or higher, left out where fewer than `MIN_OF` characters were counted. */
class ReadFeatShares
{
    public const int MIN_OF = 20;

    /**
     * @param  list<string>|null  $keys  the feats to read, or every feat
     * @return array<string, list<float>> by feat key, the five shares uncommon first; a feat with no share is not in it
     */
    public function handle(?array $keys = null): array
    {
        $shares = [];
        $rows = DB::table('feat_shares')->where('of', '>=', self::MIN_OF)->when($keys !== null, fn ($query) => $query->whereIn('feat', $keys ?? []))
            ->orderBy('feat')->orderBy('tier')->get(['feat', 'tier', 'share']);
        foreach ($rows as $row) {
            $shares[(string) $row->feat][(int) $row->tier - 1] = (float) $row->share;
        }

        return array_filter(array_map(fn (array $tiers): array => array_values($tiers), $shares), fn (array $tiers): bool => count($tiers) === 5);
    }
}
