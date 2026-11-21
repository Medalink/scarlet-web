<?php

namespace App\Actions\Ingest;

use App\Jobs\RefreshRollups;
use App\Models\Part;
use App\Support\DecodedSession;
use App\Support\PartTool;

class DecodeSession
{
    public function __construct(
        private readonly PartTool $tool,
        private readonly StoreDecodedSession $store,
    ) {}

    /**
     * Decode the stored parts of one session of one character and replace the session's rows with the result.
     * With no part left it removes the session's rows. Safe to run twice: the same parts give the same rows.
     */
    public function handle(int $characterId, int $sessionStart): void
    {
        $bodies = [];
        $parts = Part::query()
            ->where('character_id', $characterId)
            ->where('session_start', $sessionStart)
            ->orderBy('group_key')
            ->orderBy('id')
            ->get(['body']);
        foreach ($parts as $part) {
            $bodies[] = $part->body();
        }

        $decoded = $bodies === [] ? DecodedSession::empty() : DecodedSession::parse($this->tool->decode($bodies));

        $window = $this->store->handle($characterId, $sessionStart, $decoded);
        if ($window !== null) {
            RefreshRollups::dispatch($window['from'], $window['until']);
        }
    }
}
