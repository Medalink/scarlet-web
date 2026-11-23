<?php

namespace Database\Seeders;

use App\Models\Realm;
use Database\Seeders\Population\World;
use Illuminate\Database\Seeder;

/** One realm for each ruleset the characters of the population live on, besides the two Normal realms the migrations make. */
class RealmSeeder extends Seeder
{
    public function run(): void
    {
        foreach (World::REALMS as $kind => $ids) {
            foreach ($ids as $id) {
                Realm::query()->insertOrIgnore([
                    'id' => $id,
                    'kind' => $kind,
                    'name' => World::REALM_NAMES[$id] ?? null,
                    'source' => Realm::SOURCE_SEED,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
