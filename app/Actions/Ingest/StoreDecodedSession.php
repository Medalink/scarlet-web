<?php

namespace App\Actions\Ingest;

use App\Support\DecodedSession;
use App\Support\SessionRows;
use Illuminate\Support\Facades\DB;

class StoreDecodedSession
{
    public function __construct(
        private readonly ResolveRuleset $rulesets,
        private readonly DeleteFactRows $deleteRows,
        private readonly InsertFactRows $insertRows,
        private readonly UpsertDictionaries $dictionaries,
        private readonly UpsertTalentLayouts $layouts,
        private readonly SummarizeTalents $talents,
        private readonly MarkSessionJoins $joins,
        private readonly RebuildCharacterLevels $levels,
        private readonly ResolveDungeonJoins $dungeonJoins,
        private readonly SummarizeCharacter $summaries,
        private readonly SessionRows $sessionRows,
        private readonly MeasureDistance $distance,
    ) {}

    /**
     * Replace one session's rows with a fresh decode, in one short transaction: delete the session's rows,
     * insert the new ones, write the session row and the dictionaries, then bring the character's levels,
     * dungeon runs and summary up to date. A character is locked while this runs, so sessions of one
     * character are stored one at a time. Safe to run twice.
     *
     * @return array{from: int, until: int}|null epoch seconds the rollups must look at again
     */
    public function handle(int $characterId, int $sessionStart, DecodedSession $decoded): ?array
    {
        $ruleset = $decoded->summary === null ? null : $this->rulesets->handle($characterId, $decoded->summary);

        return DB::transaction(function () use ($characterId, $sessionStart, $decoded, $ruleset): ?array {
            if (! DB::table('characters')->where('id', $characterId)->lockForUpdate()->exists()) {
                return null;
            }

            $old = $this->sessionRows->range($characterId, $sessionStart);
            $namesCounted = $this->sessionRows->namesCounted($characterId, $sessionStart);
            $this->deleteRows->handle([$characterId], null, $sessionStart);

            $range = $this->union($old, $this->replace($characterId, $sessionStart, $ruleset?->id, $decoded, $namesCounted));
            $this->joins->handle($characterId);
            $range = $this->union($range, $this->levels->handle($characterId));
            $this->dungeonJoins->handle($characterId, $sessionStart);
            $this->summaries->handle($characterId);
            $this->talents->handle($characterId);

            return $range;
        });
    }

    /**
     * Write the decoded rows, or remove the session row when there is nothing to write.
     *
     * @return array{from: int, until: int}|null the span of the new session row
     */
    private function replace(int $characterId, int $sessionStart, ?int $rulesetId, DecodedSession $decoded, bool $namesCounted): ?array
    {
        if ($decoded->summary === null || $rulesetId === null) {
            $this->sessionRows->delete($characterId, $sessionStart);

            return null;
        }

        $counted = ($decoded->summary['flags'] ?? []) === [];
        $this->insertRows->handle($characterId, $sessionStart, $rulesetId, $counted, $decoded);
        $span = $this->sessionRows->write($characterId, $sessionStart, $rulesetId, $counted, $decoded->summary);
        $this->distance->handle($characterId, $sessionStart);
        $this->dictionaries->handle($characterId, $decoded, countVotes: ! $namesCounted);
        $this->layouts->handle($decoded, countUpload: ! $namesCounted);
        DB::table('parts')->where('character_id', $characterId)->where('session_start', $sessionStart)->update(['decoded_at' => now()]);

        return $span;
    }

    /**
     * @param  array{from: int, until: int}|null  $a
     * @param  array{from: int, until: int}|null  $b
     * @return array{from: int, until: int}|null
     */
    private function union(?array $a, ?array $b): ?array
    {
        if ($a === null || $b === null) {
            return $a ?? $b;
        }

        return ['from' => min($a['from'], $b['from']), 'until' => max($a['until'], $b['until'])];
    }
}
