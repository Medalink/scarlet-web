<?php

namespace App\Models;

use App\Support\Database\BinaryValue;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * One copy of the Scarlet app on one PC, known by its Ed25519 public key.
 *
 * @property string $id
 * @property string|null $name
 * @property int|null $account_id
 * @property Carbon|null $revoked_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $linked_at
 * @property string|null $client
 * @property Carbon $created_at
 */
class Install extends Model
{
    use HasUuids;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['revoked_at' => 'datetime', 'last_seen_at' => 'datetime', 'linked_at' => 'datetime'];
    }

    public function publicKey(): string
    {
        return (string) BinaryValue::read($this->attributes['public_key'] ?? null);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsToMany<Character, $this> */
    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'install_character')->withPivot('created_at');
    }
}
