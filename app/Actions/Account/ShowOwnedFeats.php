<?php

namespace App\Actions\Account;

use App\Actions\Feats\ShowCharacterFeats;
use App\Models\Account;
use App\Models\Character;
use Illuminate\Auth\Access\AuthorizationException;

class ShowOwnedFeats
{
    public function __construct(private readonly ShowCharacterFeats $feats) {}

    /**
     * The feats of one of the account's characters as its owner sees them, gold feats included, with the character
     * the feat wall needs.
     *
     * @return array{character: Character, feats: list<array{key: string, name: string, value: int|float, tier: int, tier_name: string|null, threshold: int|null, next: int|null, pinned: bool}>}
     *
     * @throws AuthorizationException when the character is not the account's
     */
    public function handle(Account $account, int $characterId): array
    {
        $character = $account->characters()->whereKey($characterId)->first()
            ?? throw new AuthorizationException('This character is not on your account.');

        return ['character' => $character, 'feats' => $this->feats->handle($character, true)];
    }
}
