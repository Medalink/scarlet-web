<?php

namespace App\Actions\Ingest;

use App\Models\Part;
use App\Support\Database\PartIds;
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
        private readonly UpsertReferenceFacts $reference,
        private readonly UpsertTalentLayouts $layouts,
        private readonly SummarizeTalents $talents,
        private readonly SummarizeBaselines $baselines,
        private readonly MarkSessionJoins $joins,
        private readonly RebuildCharacterLevels $levels,
        private readonly FlagCompleteLevelMoney $moneyLevels,
        private readonly ResolveDungeonJoins $dungeonJoins,
        private readonly SummarizeCharacter $summaries,
        private readonly SessionRows $sessionRows,
        private readonly MeasureDistance $distance,
        private readonly MarkPartsDecoded $markDecoded,
        private readonly InvalidateReconciles $reconciles,
    ) {}

    /**
     * Replace one session's rows with a fresh decode, in one short transaction: delete the session's rows,
     * insert the new ones, write the session row and the dictionaries, then bring the character's levels,
     * dungeon runs and summary up to date. The parts the decode read are marked decoded, also when they hold nothing
     * to store, so the sweep does not queue them again; a part whose row is gone by the time the character is locked
     * is dropped instead, so a decode never marks parts it no longer sees. A character is locked while this runs, so
     * sessions of one character are stored one at a time. The addon days of the character and of the characters linked
     * to it are marked as not reconciled in the same transaction, since the rows its claims are checked against
     * changed. Safe to run twice.
     *
     * @param  list<string>  $partIds  lower-case hex ids of the parts `$decoded` came from
     * @return array{from: int, until: int}|null epoch seconds the rollups must look at again
     */
    public function handle(int $characterId, int $sessionStart, DecodedSession $decoded, array $partIds): ?array
    {
        $ruleset = $decoded->summary === null ? null : $this->rulesets->handle($characterId, $decoded->summary);

        return DB::transaction(function () use ($characterId, $sessionStart, $decoded, $ruleset, $partIds): ?array {
            if (! DB::table('characters')->where('id', $characterId)->lockForUpdate()->exists()) {
                return null;
            }
            $this->reconciles->handle([$characterId]);

            $old = $this->sessionRows->range($characterId, $sessionStart);
            $namesCounted = $this->sessionRows->namesCounted($characterId, $sessionStart);
            $this->deleteRows->handle([$characterId], null, $sessionStart);

            $range = $this->union($old, $this->replace($characterId, $sessionStart, $ruleset?->id, $decoded, $namesCounted));
            $this->joins->handle($characterId);
            $range = $this->union($range, $this->levels->handle($characterId));
            $range = $this->union($range, $this->moneyLevels->handle($characterId));
            $this->dungeonJoins->handle($characterId, $sessionStart);
            $this->summaries->handle($characterId);
            $this->talents->handle($characterId);
            $this->baselines->handle($characterId);
            $this->markDecoded->handle($characterId, $sessionStart, $this->presentParts($characterId, $sessionStart, $partIds));

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
        $this->dictionaries->handle($characterId, $decoded, countVotes: ! $namesCounted, sessionStart: $sessionStart);
        $this->reference->handle($characterId, $decoded, $sessionStart);
        $this->layouts->handle($characterId, $decoded, countUpload: ! $namesCounted);

        return $span;
    }

    /**
     * The ids read for the session whose part rows are still there under the lock: the set the store marks decoded.
     *
     * @param  list<string>  $partIds  lower-case hex ids
     * @return list<string>
     */
    private function presentParts(int $characterId, int $sessionStart, array $partIds): array
    {
        if ($partIds === []) {
            return [];
        }

        $query = Part::query()
            ->where('character_id', $characterId)
            ->where('session_start', $sessionStart)
            ->selectRaw("encode(id, 'hex') as hex");

        /** @var list<string> */
        return PartIds::whereIn($query, $partIds)->toBase()->pluck('hex')->all();
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
