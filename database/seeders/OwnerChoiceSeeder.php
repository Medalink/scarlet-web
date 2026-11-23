<?php

namespace Database\Seeders;

use App\Models\Character;
use App\Support\Feat;
use Database\Seeders\Population\PopulationPlan;
use Database\Seeders\Population\Rng;
use Database\Seeders\Population\Roster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** What the owners chose for their characters: who is public, who shows copper, whose best feats are pinned. */
class OwnerChoiceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Roster::plan()->characters as $plan) {
            $character = Character::query()->where('name_key', Character::keyFor($plan->name))->first();

            if ($character === null) {
                continue;
            }
            $rng = Rng::for('choice:'.$plan->name);
            $pinned = $rng->chance(30) ? $this->bestFeats($character) : null;

            $character->forceFill([
                'is_public' => PopulationPlan::crowded($plan) || $rng->chance(70),
                'show_economy' => $plan->persona->showsEconomy() || $rng->chance(15),
                'pinned_feats' => $pinned === [] ? null : $pinned,
            ])->save();
        }
    }

    /** @return list<string> */
    private function bestFeats(Character $character): array
    {
        return DB::table('character_feats')
            ->where('character_id', $character->id)
            ->orderByDesc('value')
            ->limit(3)
            ->pluck('feat')
            ->map(fn ($feat): string => (string) $feat)
            ->filter(fn (string $feat): bool => Feat::tryFrom($feat) !== null)
            ->values()
            ->all();
    }
}
