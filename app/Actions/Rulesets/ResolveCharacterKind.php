<?php

namespace App\Actions\Rulesets;

use App\Models\Character;
use App\Models\Realm;
use App\Models\RulesetKind;

class ResolveCharacterKind
{
    /**
     * The ruleset a character plays, or null while nothing names it: what the Forever Armory said for the character,
     * else the kind the site's owner declared for it, else the ruleset of its home realm. A session that arrives before the character has a home realm brings its
     * own realm id, so the first session of a character on a known realm is filed under its kind at once.
     */
    public function handle(int $characterId, ?int $sessionRealmId = null): ?RulesetKind
    {
        $character = Character::query()->whereKey($characterId)->first(['id', 'armory_kind', 'declared_kind', 'home_realm_id']);
        if ($character === null) {
            return null;
        }
        $named = RulesetKind::tryFrom((string) ($character->armory_kind ?? $character->declared_kind));
        if ($named !== null) {
            return $named;
        }

        $realmId = $character->home_realm_id ?? $sessionRealmId;

        return $realmId === null ? null : Realm::query()->find($realmId)?->kind();
    }
}
