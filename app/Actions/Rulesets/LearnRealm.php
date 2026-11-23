<?php

namespace App\Actions\Rulesets;

use App\Models\Realm;
use App\Models\RulesetKind;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LearnRealm
{
    /**
     * Record the ruleset of a realm a character on it was found to play. An observation is a character whose home
     * realm is this one and whose Armory lookup named a kind, credited to every install that uploaded the character;
     * the characters and their install links are the whole ledger, so a character that is looked up again or moves to
     * another realm stops counting. A kind becomes the realm's ruleset when the kind was seen on distinct voters up
     * to the quorum (`scarlet.armory.realm_quorum`), a voter being an account, or an install that no account is linked to,
     * so neither one install, one claimed name, nor all the installs of one account can decide a realm-wide fact. A learned
     * kind that another corroborated kind outruns is re-decided, which files the realm's sessions under the new kind; a realm the site's owner seeded keeps its kind.
     *
     * @return bool whether the realm's ruleset changed
     */
    public function handle(int $realmId, RulesetKind $kind, string $source): bool
    {
        Realm::query()->insertOrIgnore(['id' => $realmId, 'kind' => null, 'source' => $source, 'created_at' => now(), 'updated_at' => now()]);
        $realm = Realm::query()->findOrFail($realmId);
        $counts = $this->votersPerKind($realmId);
        $quorum = max(1, (int) config('scarlet.armory.realm_quorum', 2));
        if (($counts[$kind->value] ?? 0) < $quorum) {
            if ($realm->kind !== null && $realm->kind !== $kind->value) {
                Log::warning('A character on a realm plays another ruleset than the realm is recorded as, and no quorum of installs says otherwise yet.', ['realm' => $realmId, 'recorded' => $realm->kind, 'found' => $kind->value]);
            }

            return false;
        }
        $best = $this->majority($counts, $realm->kind);
        if ($best === $realm->kind) {
            return false;
        }
        if ($realm->source === Realm::SOURCE_SEED) {
            Log::warning('The characters of a seeded realm play another ruleset than the seed records.', ['realm' => $realmId, 'seeded' => $realm->kind, 'found' => $best]);

            return false;
        }
        if ($realm->kind !== null) {
            Log::info('A realm\'s ruleset is re-decided by the installs that play it.', ['realm' => $realmId, 'was' => $realm->kind, 'now' => $best]);
        }
        $realm->update(['kind' => $best, 'source' => $source]);

        return true;
    }

    /**
     * @param  array<string, int>  $counts  voters per kind
     * @return string the kind with the most voters behind it; the kind the realm already carries wins a tie
     */
    private function majority(array $counts, ?string $incumbent): string
    {
        $ranked = $counts;
        ksort($ranked);
        $best = '';
        foreach ($ranked as $value => $count) {
            if ($best === '' || $count > $ranked[$best]) {
                $best = (string) $value;
            }
        }
        if ($incumbent !== null && isset($ranked[$incumbent]) && $ranked[$incumbent] >= $ranked[$best]) {
            return $incumbent;
        }

        return $best;
    }

    /** @return array<string, int> the distinct voters behind every kind the realm's characters were found to play */
    private function votersPerKind(int $realmId): array
    {
        $rows = DB::table('characters')
            ->join('install_character', 'install_character.character_id', '=', 'characters.id')
            ->join('installs', 'installs.id', '=', 'install_character.install_id')
            ->where('characters.home_realm_id', $realmId)
            ->whereNotNull('characters.armory_kind')
            ->groupBy('characters.armory_kind')
            ->selectRaw("characters.armory_kind, count(distinct coalesce('account:' || installs.account_id, 'install:' || installs.id::text)) as voters")
            ->get();

        return $rows->pluck('voters', 'armory_kind')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }
}
