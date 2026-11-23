<?php

namespace App\Actions\Ingest;

use App\Support\DecodedSession;
use App\Support\SharedKind;

class UpsertReferenceFacts
{
    public function __construct(private readonly VoteSharedRows $shared) {}

    /**
     * Write the recipes and spells a session read. They are the same for every uploader, so their fields are voted
     * on by the installs behind the character (`VoteSharedRows`) and no one install sets what a page serves.
     * `seen_at` is the server time of the decode that last changed a row, never the line's `at`.
     */
    public function handle(int $characterId, DecodedSession $decoded): void
    {
        $this->shared->handle($characterId, SharedKind::Recipe, $decoded->recipes);
        $this->shared->handle($characterId, SharedKind::Spell, $decoded->spells);
    }
}
