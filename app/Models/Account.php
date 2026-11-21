<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A signed-in person. Sign-in comes later; for now an account only gives installs and characters an owner. */
class Account extends Model
{
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
}
