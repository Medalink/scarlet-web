<?php

namespace App\Actions\Feats;

use App\Jobs\RefreshClassStandings;
use App\Models\Character;
use App\Support\PlayerClass;

/** Queues the class standings of one character whose class the game has. */
class QueueClassStandings
{
    /** @return bool whether a job was queued */
    public function handle(int $characterId): bool
    {
        $class = PlayerClass::tryFrom(strtoupper((string) Character::query()->whereKey($characterId)->value('class')));
        if ($class === null) {
            return false;
        }
        RefreshClassStandings::dispatch($characterId);

        return true;
    }
}
