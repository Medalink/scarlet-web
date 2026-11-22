<?php

namespace App\Actions\Ingest;

use App\Jobs\ReconcileCharacterClaims;
use App\Jobs\RefreshCharacterFeats;
use App\Jobs\RefreshRollups;
use App\Models\AddonDay;
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
        private readonly StoreFishingLines $fishing,
        private readonly StoreAddonClaims $claims,
        private readonly AddonClaimSiblings $siblings,
    ) {}

    /**
     * Decode the stored parts of one session of one character and replace the session's rows with the result.
     * With no part left it removes the session's rows. Safe to run twice: the same parts give the same rows.
     * The parts of groups `imports`, `fishing` and `counts` are keyed by the start of a UTC day and go to their own
     * tables, and the catches in a session's `addons` parts go to the fishing lines too. Every decode replaces the
     * key's fishing lines, so a removed part takes its lines with it. A key that held `counts` parts decodes them again
     * even when none is left, which drops the claims only they carried. Whatever the decode did, the claims of the
     * character and of the characters linked to it are reconciled afterwards (docs/DESIGN.md, Reconcile).
     */
    public function handle(int $characterId, int $sessionStart): void
    {
        $this->decode($characterId, $sessionStart);
        if ($this->siblings->handle($characterId) !== []) {
            ReconcileCharacterClaims::dispatch($characterId);
        }
    }

    private function decode(int $characterId, int $sessionStart): void
    {
        $bodies = [];
        $imported = [];
        $fishing = [];
        $catches = [];
        $counts = [];
        $partIds = [];
        $importedIds = [];
        $fishingIds = [];
        $countsIds = [];
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
            if ($part->group_key === SharingGroup::FISHING) {
                $fishing[] = $part->body();
                $fishingIds[] = $id;

                continue;
            }
            if ($part->group_key === SharingGroup::COUNTS) {
                $counts[] = $part->body();
                $countsIds[] = $id;

                continue;
            }
            if ($part->group_key === SharingGroup::ADDONS) {
                $catches[] = $part->body();
            }
            $bodies[] = $part->body();
            $partIds[] = $id;
        }

        $fishingInput = [...$fishing, ...$catches];
        $this->fishing->handle($characterId, $sessionStart, $fishingInput === [] ? [] : $this->tool->decodeFishing($fishingInput), $fishingIds);

        if ($imported !== [] || $parts->isEmpty()) {
            $this->imports->handle($characterId, $sessionStart, $imported === [] ? [] : $this->tool->decodeImports($imported), $importedIds);
        }
        if ($counts !== [] || AddonDay::query()->where('character_id', $characterId)->where('day_key', $sessionStart)->exists()) {
            $this->claims->handle($characterId, $sessionStart, $counts === [] ? [] : $this->tool->decodeCounts($counts), $countsIds);
        }
        if ($bodies === [] && ($imported !== [] || $fishing !== [] || $counts !== [])) {
            return;
        }

        $decoded = $bodies === [] ? DecodedSession::empty() : DecodedSession::parse($this->tool->decode($bodies));

        $window = $this->store->handle($characterId, $sessionStart, $decoded, $partIds);
        RefreshCharacterFeats::dispatch($characterId);
        if ($window !== null) {
            RefreshRollups::dispatch($window['from'], $window['until']);
        }
    }
}
