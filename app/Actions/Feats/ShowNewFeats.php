<?php

namespace App\Actions\Feats;

use App\Models\Character;
use App\Support\Feat;
use Illuminate\Support\Facades\DB;

/** The feats of a character whose tier is above the tier its owner was last shown. Only owners read it, so the gold feats are in. */
class ShowNewFeats
{
    /**
     * Highest tier first, then the order of `Feat`. `from` is the tier shown before (0 for a feat earned for the first
     * time, with `from_name` null).
     *
     * @return list<array{key: string, name: string, icon: string, from: int, from_name: string|null, tier: int, tier_name: string}>
     */
    public function handle(Character $character): array
    {
        $order = array_flip(array_map(fn (Feat $feat): string => $feat->value, Feat::cases()));
        $new = [];
        foreach (DB::table('character_feats')->where('character_id', $character->id)->get(['feat', 'value', 'seen_tier']) as $row) {
            $feat = Feat::tryFrom((string) $row->feat);
            if ($feat === null) {
                continue;
            }
            $tier = $feat->held((float) $row->value);
            $from = (int) $row->seen_tier;
            if ($tier <= $from) {
                continue;
            }
            $new[] = [
                'key' => $feat->value, 'name' => $feat->label(), 'icon' => $feat->icon(),
                'from' => $from, 'from_name' => Feat::TIERS[$from - 1] ?? null,
                'tier' => $tier, 'tier_name' => Feat::TIERS[$tier - 1],
            ];
        }
        usort($new, fn (array $a, array $b): int => [$b['tier'], $order[$a['key']]] <=> [$a['tier'], $order[$b['key']]]);

        return $new;
    }
}
