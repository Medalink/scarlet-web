<?php

namespace App\Actions\Rulesets;

use App\Models\Character;
use App\Models\Realm;
use App\Models\RulesetKind;

class ResolveCharacterKind
{
    /**
     * The ruleset a character plays, or null while nothing names it: the kind the site's owner declared for it, else
     * the ruleset of its home realm. What the Forever Armory said for the character counts only through its realm
     * (`LearnRealm`), where installs that no account links agree on it, because the character's name is the claim of one
     * install and the page of that name is not tied to who uploaded it. A session that arrives before the character has a
     * home realm brings its own realm id, so the first session of a character on a known realm is filed under its kind
     * at once.
     */
    public function handle(int $characterId, ?int $sessionRealmId = null): ?RulesetKind
    {
        $character = Character::query()->whereKey($characterId)->first(['id', 'declared_kind', 'home_realm_id']);
        if ($character === null) {
            return null;
        }
        $named = RulesetKind::tryFrom((string) $character->declared_kind);
        if ($named !== null) {
            return $named;
        }

        $realmId = $character->home_realm_id ?? $sessionRealmId;

        return $realmId === null ? null : Realm::query()->find($realmId)?->kind();
    }
}
