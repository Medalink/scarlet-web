<?php

namespace App\Actions\Account;

use App\Actions\Feats\ShowCharacterFeats;
use App\Actions\Feats\ShowNewFeats;
use App\Models\Account;
use App\Models\Character;
use Illuminate\Auth\Access\AuthorizationException;

class ShowOwnedFeats
{
    public function __construct(private readonly ShowCharacterFeats $feats, private readonly ShowNewFeats $new) {}

    /**
     * The feats of one of the account's characters as its owner sees them, gold feats included, with the character
     * the feat wall needs and the feats whose tier the owner has not been shown (`new`).
     *
     * @return array{character: Character, feats: list<array{key: string, name: string, value: int|float, tier: int, tier_name: string|null, threshold: int|null, next: int|null, pinned: bool}>, new: list<array{key: string, name: string, icon: string, from: int, from_name: string|null, tier: int, tier_name: string}>}
     *
     * @throws AuthorizationException when the character is not the account's
     */
    public function handle(Account $account, int $characterId): array
    {
        $character = $account->characters()->whereKey($characterId)->first()
            ?? throw new AuthorizationException('This character is not on your account.');

        return ['character' => $character, 'feats' => $this->feats->handle($character, true), 'new' => $this->new->handle($character)];
    }
}
