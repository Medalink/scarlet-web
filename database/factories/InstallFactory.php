<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Install;
use App\Support\Database\BinaryValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Install>
 */
class InstallFactory extends Factory
{
    public const string CLIENT = 'scarlet-app/0.0.0-dev (windows)';

    protected $model = Install::class;

    /** The public key a label always gets, so a seeded install is found again by its label. */
    public static function keyFor(string $label): string
    {
        return hash('sha256', 'scarlet-seed-install:'.$label, binary: true);
    }

    public function definition(): array
    {
        return [
            'name' => 'Scarlet app',
            'client' => self::CLIENT,
            'origin' => '127.0.0.1',
            'last_seen_at' => now(),
        ];
    }

    public function labelled(string $label): static
    {
        return $this->state(fn (): array => ['public_key' => BinaryValue::bind(self::keyFor($label))]);
    }

    public function linkedTo(Account $account): static
    {
        return $this->state(fn (): array => ['account_id' => $account->id, 'linked_at' => now()]);
    }

    public function unlinked(): static
    {
        return $this->state(fn (): array => ['account_id' => null, 'linked_at' => null]);
    }
}
