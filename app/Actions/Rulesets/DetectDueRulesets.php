<?php

namespace App\Actions\Rulesets;

use App\Models\Character;
use App\Support\ArmoryLookup;

class DetectDueRulesets
{
    public function __construct(
        private readonly DetectArmoryRuleset $detect,
        private readonly RefileSessions $refile,
    ) {}

    /**
     * Look up every character that is due, one request at a time with a pause between them, until none is due, the
     * Armory asks to slow down, or the time budget is spent. Sessions of characters whose kind was found are filed
     * under it. A character is due while it has no kind and its next look has come.
     *
     * @return array<string, int> looks by outcome
     */
    public function handle(int $budgetSeconds): array
    {
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

        if ($done[ArmoryLookup::Found->value] > 0) {
            $this->refile->handle();
        }

        return $done;
    }
}
