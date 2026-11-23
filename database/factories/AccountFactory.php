<?php

namespace Database\Factories;

use App\Actions\Auth\SignIn;
use App\Models\Account;
use App\Models\Identity;
use App\Models\IdentityProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [];
    }

    /** The account the development sign-in opens. */
    public function developer(): static
    {
        return $this->signedInWith(IdentityProvider::Development, SignIn::DEVELOPMENT_SUBJECT);
    }

    /** An account that signs in with one provider under a subject of its own. */
    public function signedInWith(IdentityProvider $provider, string $subject): static
    {
        return $this->afterCreating(function (Account $account) use ($provider, $subject): void {
            Identity::query()->create([
                'account_id' => $account->id,
                'provider' => $provider,
                'subject' => $subject,
            ]);
        });
    }
}
