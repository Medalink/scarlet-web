<?php

namespace App\Support;

use App\Models\Install;
use App\Models\Part;
use App\Models\PartClaim;
use App\Support\Database\PartIds;

/**
 * The installs that listed a part. A part stays on the server while one install claims it, so a character played
 * on two PCs keeps what each PC uploaded.
 */
final class PartClaims
{
    private const int IDS_PER_QUERY = 5000;

    /**
     * Claim the stored parts among these ids for the install. An id the server does not hold is skipped.
     *
     * @param  list<string>  $hex
     */
    public static function add(Install $install, array $hex): void
    {
        foreach (array_chunk($hex, self::IDS_PER_QUERY) as $chunk) {
            $stored = PartIds::whereIn(Part::query(), $chunk)->select('parts.id')->selectRaw('?::uuid', [$install->id]);
            PartClaim::query()->insertOrIgnoreUsing(['part_id', 'install_id'], $stored);
        }
    }
}
