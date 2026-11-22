<?php

namespace App\Actions\Ingest;

use App\Jobs\RefreshCharacterFeats;
use App\Jobs\RefreshRollups;
use App\Models\Part;
use App\Support\Database\BinaryValue;
use App\Support\DecodedSession;
use App\Support\PartTool;
use App\Support\SharingGroup;

class DecodeSession
{
    public function __construct(
        private readonly PartTool $tool,
        private readonly StoreDecodedSession $store,
        private readonly StoreImportedRecords $imports,
    ) {}

    /**
     * Decode the stored parts of one session of one character and replace the session's rows with the result.
     * With no part left it removes the session's rows. Safe to run twice: the same parts give the same rows.
     * The parts of group `imports` are keyed by the start of a UTC day and go to their own table.
     */
    public function handle(int $characterId, int $sessionStart): void
    {
        $bodies = [];
        $imported = [];
        $partIds = [];
        $importedIds = [];
        $parts = Part::query()
            ->where('character_id', $characterId)
            ->where('session_start', $sessionStart)
            ->orderBy('group_key')
            ->orderBy('id')
            ->get(['id', 'group_key', 'body']);
        foreach ($parts as $part) {
            $id = bin2hex((string) BinaryValue::read($part->getAttributes()['id']));
            if ($part->group_key === SharingGroup::IMPORTS) {
                $imported[] = $part->body();
                $importedIds[] = $id;

                continue;
            }
            $bodies[] = $part->body();
            $partIds[] = $id;
        }

        if ($imported !== []) {
            $this->imports->handle($characterId, $sessionStart, $this->tool->decodeImports($imported), $importedIds);
            if ($bodies === []) {
                return;
            }
        }

        $decoded = $bodies === [] ? DecodedSession::empty() : DecodedSession::parse($this->tool->decode($bodies));

        $window = $this->store->handle($characterId, $sessionStart, $decoded, $partIds);
        RefreshCharacterFeats::dispatch($characterId);
        if ($window !== null) {
            RefreshRollups::dispatch($window['from'], $window['until']);
        }
    }
}
