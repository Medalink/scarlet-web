<?php

namespace App\Actions\Feats;

use App\Models\Character;

/** The characters an account's crests count: the account's own and those its installs sent (`Character::ownedBy`, over every install of the account). */
class ListAccountCharacterIds
{
    /** @return list<int> lowest id first */
    public function handle(int $accountId): array
    {
        return array_values(Character::query()->ownedByAccount($accountId)->orderBy('characters.id')->pluck('characters.id')->map(fn ($id): int => (int) $id)->all());
    }
}
