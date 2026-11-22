<?php

namespace App\Actions\Feats;

use App\Models\Character;
use App\Support\Feat;
use Illuminate\Support\Facades\DB;

class MarkFeatsSeen
{
    /**
     * Record the tiers an owner was shown. Each feat's seen tier rises to the shown tier, never past the tier its value
     * holds now and never down; a key that is not a feat is ignored. Safe to run twice and at once: each feat is one
     * guarded UPDATE.
     *
     * @param  array<array-key, int>  $shown  feat key => the tier that was shown
     */
    public function handle(Character $character, array $shown): void
    {
        $feats = [];
        foreach ($shown as $key => $tier) {
            $feat = Feat::tryFrom((string) $key);
            if ($feat !== null) {
                $feats[$feat->value] = $tier;
            }
        }
        if ($feats === []) {
            return;
        }

        DB::transaction(function () use ($character, $feats): void {
            $values = DB::table('character_feats')->where('character_id', $character->id)->whereIn('feat', array_keys($feats))->pluck('value', 'feat');
            foreach ($values as $key => $value) {
                $target = min($feats[$key], Feat::from((string) $key)->held((float) $value));
                DB::table('character_feats')->where('character_id', $character->id)->where('feat', $key)->where('seen_tier', '<', $target)->update(['seen_tier' => $target]);
            }
        });
    }
}
