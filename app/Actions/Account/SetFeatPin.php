<?php

namespace App\Actions\Account;

use App\Models\Account;
use App\Models\Character;
use App\Support\Feat;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetFeatPin
{
    /**
     * Pin a feat to the top of one of the account's characters, or unpin it. A pin goes last in the order; pinning a
     * pinned feat or unpinning one that is not pinned changes nothing.
     *
     * @throws AuthorizationException when the character is not the account's
     * @throws ValidationException when the feat would be pinned past `Character::MAX_PINNED_FEATS`
     */
    public function handle(Account $account, int $characterId, Feat $feat, bool $on): void
    {
        DB::transaction(function () use ($account, $characterId, $feat, $on): void {
            $character = $account->characters()->whereKey($characterId)->lockForUpdate()->first()
                ?? throw new AuthorizationException('This character is not on your account.');

            $pinned = array_values(array_filter($character->pinned_feats ?? [], fn (string $key): bool => Feat::tryFrom($key) !== null));
            if (in_array($feat->value, $pinned, true) === $on) {
                return;
            }
            if (! $on) {
                $pinned = array_values(array_diff($pinned, [$feat->value]));
            }
            if ($on && count($pinned) >= Character::MAX_PINNED_FEATS) {
                throw ValidationException::withMessages(['feat' => 'Three feats are pinned. Unpin one first.']);
            }
            if ($on) {
                $pinned[] = $feat->value;
            }

            $character->pinned_feats = $pinned;
            $character->save();
        });
    }
}
