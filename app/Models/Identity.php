<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One way an account signs in: the provider and the provider's stable subject id. Nothing else about the
 * person is stored.
 *
 * @property int $id
 * @property int $account_id
 * @property IdentityProvider $provider
 * @property string $subject
 * @property Carbon $created_at
 */
class Identity extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['provider' => IdentityProvider::class];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
