<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public const float SETSEED = 0.20261123;

    /**
     * The layers of the local population, each a top-up that only creates what is missing, in the order they need:
     * realms of every ruleset, accounts and installs, the recordings that go through the real ingest, and the
     * choices the owners of the characters made.
     */
    public const array LAYERS = [
        RealmSeeder::class,
        AccountSeeder::class,
        RecordingSeeder::class,
        OwnerChoiceSeeder::class,
    ];

    public function run(): void
    {
        DB::select('SELECT setseed(?)', [self::SETSEED]);

        $this->call(self::LAYERS);
    }
}
