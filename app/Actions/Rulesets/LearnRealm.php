<?php

namespace App\Actions\Rulesets;

use App\Models\Realm;
use App\Models\RulesetKind;
use Illuminate\Support\Facades\Log;

class LearnRealm
{
    /**
     * Record the ruleset of a realm a character on it was found to play. A realm that already has a ruleset keeps it:
     * a disagreement is logged, since one realm has one ruleset and one of the two answers is wrong.
     */
    public function handle(int $realmId, RulesetKind $kind, string $source): void
    {
        Realm::query()->insertOrIgnore(['id' => $realmId, 'kind' => null, 'source' => $source, 'created_at' => now(), 'updated_at' => now()]);
        $realm = Realm::query()->findOrFail($realmId);
        if ($realm->kind === null) {
            $realm->update(['kind' => $kind->value, 'source' => $source]);

            return;
        }
        if ($realm->kind !== $kind->value) {
            Log::warning('A character on a realm plays another ruleset than the realm is recorded as.', ['realm' => $realmId, 'recorded' => $realm->kind, 'found' => $kind->value]);
        }
    }
}
