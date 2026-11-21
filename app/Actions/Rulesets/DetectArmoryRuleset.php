<?php

namespace App\Actions\Rulesets;

use App\Models\Character;
use App\Models\Realm;
use App\Models\RulesetKind;
use App\Support\ArmoryLookup;
use App\Support\ArmoryPage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DetectArmoryRuleset
{
    public function __construct(private readonly LearnRealm $realms) {}

    /**
     * Ask the Forever Armory which ruleset a character plays, with one request. The answer is permanent (a ruleset
     * is a permanent choice), so a character that has one is never asked again. A character the Armory does not
     * hold is asked again after a day, and weekly once it has been missed a week running. A page that names a
     * ruleset this code has no kind for keeps the raw slug and counts as not identified. Learns the ruleset of the
     * character's home realm from a kind it finds.
     */
    public function handle(Character $character): ArmoryLookup
    {
        $url = ArmoryPage::url((string) config('scarlet.armory.region'), $character->name_key);

        try {
            $response = Http::withUserAgent((string) config('scarlet.armory.user_agent'))->timeout((int) config('scarlet.armory.timeout'))->get($url);
        } catch (ConnectionException) {
            return $this->transient($character);
        }
        if ($response->status() === 429 || in_array($response->status(), [502, 503, 504], true)) {
            return $this->transient($character);
        }

        $found = $response->successful() ? ArmoryPage::ruleset($response->body(), $character->name_key) : null;
        if ($found === null) {
            return $this->missing($character);
        }

        $kind = RulesetKind::fromArmory($found['slug']);
        $character->update([
            'armory_kind' => $kind?->value,
            'armory_slug' => $found['slug'],
            'armory_checked_at' => now(),
            'armory_next_at' => $kind === null ? now()->addDays(7) : null,
            'armory_misses' => 0,
        ]);
        if ($kind === null) {
            Log::warning('The Forever Armory named a ruleset this code has no kind for.', ['slug' => $found['slug'], 'id' => $found['id']]);

            return ArmoryLookup::Unknown;
        }
        if ($character->home_realm_id !== null) {
            $this->realms->handle((int) $character->home_realm_id, $kind, Realm::SOURCE_ARMORY);
        }

        return ArmoryLookup::Found;
    }

    private function transient(Character $character): ArmoryLookup
    {
        $character->update(['armory_next_at' => now()->addHour()]);

        return ArmoryLookup::Transient;
    }

    private function missing(Character $character): ArmoryLookup
    {
        $misses = (int) $character->armory_misses + 1;
        $character->update([
            'armory_checked_at' => now(),
            'armory_next_at' => now()->addDays($misses < 7 ? 1 : 7),
            'armory_misses' => $misses,
        ]);

        return ArmoryLookup::Missing;
    }
}
