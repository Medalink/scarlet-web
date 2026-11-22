<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Medalink\AppVersion\Concerns\HasReleaseNoteReadState;

/**
 * A signed-in person. Owns installs, characters, sign-in identities and API keys. The account row itself
 * holds nothing about the person.
 */
class Account extends Authenticatable
{
    use HasApiTokens;
    use HasReleaseNoteReadState;

    /** The one ability an API key carries: reading the public API with the higher rate limit. */
    public const string API_ABILITY = 'public:read';

    /** An account may hold this many API keys. */
    public const int MAX_API_KEYS = 10;

    /** @return HasMany<Install, $this> */
    public function installs(): HasMany
    {
        return $this->hasMany(Install::class);
    }

    /** @return HasMany<Character, $this> */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /** @return HasMany<Identity, $this> */
    public function identities(): HasMany
    {
        return $this->hasMany(Identity::class);
    }

    /** True when one of the account's sign-ins is listed in `scarlet.admins` (docs/DESIGN.md, Admin). */
    public function isAdmin(): bool
    {
        /** @var list<string> $admins */
        $admins = config('scarlet.admins');
        if ($admins === []) {
            return false;
        }

        return $this->identities()->get(['provider', 'subject'])
            ->contains(fn (Identity $identity): bool => in_array($identity->signInKey(), $admins, true));
    }

    /** Sessions are not remembered across browsers: the account has no remember token. */
    public function getRememberTokenName(): string
    {
        return '';
    }
}
