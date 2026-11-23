<?php

namespace Database\Seeders;

use App\Actions\Auth\SignIn;
use App\Models\Account;
use App\Models\Identity;
use App\Models\IdentityProvider;
use Database\Factories\AccountFactory;
use Database\Factories\InstallFactory;
use Database\Seeders\Population\PopulationPlan;
use Database\Seeders\Population\Roster;
use Illuminate\Database\Seeder;

/**
 * The accounts of the population and the installs that carry its characters. The first account is the one the
 * development sign-in opens, so `/account` shows a full roster; the installs after the accounts' own belong to no one.
 */
class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [];

        for ($place = 0; $place < PopulationPlan::ACCOUNTS; $place++) {
            $accounts[$place] = $this->account($place);
        }

        for ($place = 0; $place < PopulationPlan::places((int) config('scarlet.seed.characters_per_class')); $place++) {
            $account = $accounts[$place] ?? null;
            $install = Roster::install($place);

            if ($install === null) {
                $factory = InstallFactory::new()->labelled(Roster::label($place));
                $factory->state(['name' => 'Scarlet app'])->create(['account_id' => $account?->id, 'linked_at' => $account === null ? null : now()]);

                continue;
            }
            if ($account !== null && $install->account_id === null) {
                $install->forceFill(['account_id' => $account->id, 'linked_at' => now()])->save();
            }
        }
    }

    private function account(int $place): Account
    {
        $subject = $place === 0 ? SignIn::DEVELOPMENT_SUBJECT : 'seed-account-'.$place;
        $provider = $place === 0 ? IdentityProvider::Development : IdentityProvider::Google;
        $found = Identity::query()->where('provider', $provider)->where('subject', $subject)->first();

        if ($found !== null) {
            return Account::query()->findOrFail($found->account_id);
        }

        return $place === 0
            ? AccountFactory::new()->developer()->create()
            : AccountFactory::new()->signedInWith($provider, $subject)->create();
    }
}
