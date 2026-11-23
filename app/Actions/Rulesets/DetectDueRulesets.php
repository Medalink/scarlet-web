<?php

namespace App\Actions\Rulesets;

use App\Models\Character;
use App\Models\Realm;
use App\Models\RulesetKind;
use App\Support\ArmoryLookup;

class DetectDueRulesets
{
    public function __construct(
        private readonly DetectArmoryRuleset $detect,
        private readonly LearnRealm $realms,
        private readonly RefileSessions $refile,
    ) {}

    /**
     * First teach realms what the Armory already said: a character found before it had a home realm (its first
     * session with a realm id came later) names the realm it lives on. Then look up every character that is due, one request at a time with a pause between them, until none is due, the
     * Armory asks to slow down, or the time budget is spent. Sessions of characters whose kind was found are filed
     * under it. A character is due while it has no kind and its next look has come.
     *
     * @return array<string, int> looks by outcome
     */
    public function handle(int $budgetSeconds): array
    {
        $learned = $this->learnRealms();
        $deadline = microtime(true) + $budgetSeconds;
        $pause = (int) config('scarlet.armory.pause_ms') * 1000;
        $done = array_fill_keys(array_column(ArmoryLookup::cases(), 'value'), 0);

        while (microtime(true) < $deadline) {
            $character = Character::query()
                ->whereNull('armory_kind')
                ->where(fn ($due) => $due->whereNull('armory_next_at')->orWhere('armory_next_at', '<=', now()))
                ->orderBy('id')
                ->first();
            if ($character === null) {
                break;
            }
            $outcome = $this->detect->handle($character);
            $done[$outcome->value]++;
            if ($outcome === ArmoryLookup::Transient) {
                break;
            }
            usleep($pause);
        }

        if ($learned > 0 || $done[ArmoryLookup::Found->value] > 0) {
            $this->refile->handle();
        }

        return $done;
    }

    /** @return int realms whose ruleset changed */
    private function learnRealms(): int
    {
        $learned = 0;
        $unknown = Character::query()
            ->leftJoin('realms', 'realms.id', '=', 'characters.home_realm_id')
            ->whereNotNull('characters.armory_kind')
            ->whereNotNull('characters.home_realm_id')
            ->whereNull('realms.kind')
            ->select('characters.home_realm_id', 'characters.armory_kind')
            ->distinct()
            ->get();
        foreach ($unknown as $character) {
            $kind = RulesetKind::tryFrom((string) $character->armory_kind);
            if ($kind !== null && $this->realms->handle((int) $character->home_realm_id, $kind, Realm::SOURCE_ARMORY)) {
                $learned++;
            }
        }

        return $learned;
    }
}
