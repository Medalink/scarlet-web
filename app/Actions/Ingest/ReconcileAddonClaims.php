<?php

namespace App\Actions\Ingest;

use App\Models\Character;
use App\Support\AddonKind;
use App\Support\PartTool;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

class ReconcileAddonClaims
{
    private const int ATTEMPTS = 3;

    public function __construct(
        private readonly AddonClaimSiblings $siblings,
        private readonly ReadReconcileInput $inputs,
        private readonly PartTool $tool,
    ) {}

    /**
     * Decide again what every addon claim of the character counts, and of the characters linked to it whose rule 4
     * answer may change (docs/DESIGN.md, Reconcile). `scarlet-part reconcile` runs outside any transaction, one
     * character and kind at a time, on what was read without a lock. The result is written in one short transaction
     * that locks the characters lowest id first, reads the same input again and writes only when it is unchanged;
     * a change in between makes the whole run start over, three times at most. A claim row is written only where a
     * column differs, so a second run changes nothing and returns 0. The addon days of the character are stamped with
     * the version too when it holds no claims itself, or none are linked to it: there is nothing to decide, and the
     * outdated pass must not queue it again.
     *
     * @return int claims whose stored verdict changed
     */
    public function handle(int $characterId): int
    {
        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $ids = $this->siblings->handle($characterId);
            $now = time();
            $inputs = $this->read($characterId, $ids, $now);
            $verdicts = $this->verdicts($inputs);
            $changed = $this->store($characterId, $ids, $now, $inputs, $verdicts);
            if ($changed !== null) {
                return $changed;
            }
        }

        throw new RuntimeException("The addon claims linked to character {$characterId} kept changing while they were reconciled.");
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array<string, string>> the input of each character and kind
     */
    private function read(int $characterId, array $ids, int $now): array
    {
        $inputs = [];
        foreach ($ids as $id) {
            $kinds = DB::table('addon_claims')->where('character_id', $id)->distinct()->orderBy('kind')->pluck('kind');
            foreach ($kinds as $kind) {
                $inputs[$id][(string) $kind] = $this->inputs->handle($id, AddonKind::from((string) $kind), $ids, $now);
            }
        }

        return $inputs;
    }

    /**
     * @param  array<int, array<string, string>>  $inputs
     * @return array<int, list<array{id: int, status: string, counted: array<string, int>, evidence: array<string, mixed>, level: int|null, zone: int|null}>>
     */
    private function verdicts(array $inputs): array
    {
        $verdicts = [];
        foreach ($inputs as $id => $byKind) {
            foreach ($byKind as $input) {
                $verdicts[$id] = [...($verdicts[$id] ?? []), ...$this->tool->reconcile($input)];
            }
        }

        return $verdicts;
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, array<string, string>>  $inputs
     * @param  array<int, list<array{id: int, status: string, counted: array<string, int>, evidence: array<string, mixed>, level: int|null, zone: int|null}>>  $verdicts
     */
    private function store(int $characterId, array $ids, int $now, array $inputs, array $verdicts): ?int
    {
        $version = $this->tool->reconcileVersion();

        return DB::transaction(function () use ($characterId, $ids, $now, $inputs, $verdicts, $version): ?int {
            $locked = array_values(array_unique([...$ids, $characterId]));
            Character::query()->whereIn('id', $locked)->lockedInOrder()->pluck('id');
            if ($this->siblings->handle($characterId) !== $ids || $this->read($characterId, $ids, $now) !== $inputs) {
                return null;
            }

            $changed = 0;
            foreach ($verdicts as $id => $claims) {
                $sessions = $this->sessionsOfClaims($id);
                foreach ($claims as $claim) {
                    $changed += $this->write($claim, $sessions[$claim['id']] ?? null, $version);
                }
            }
            DB::table('addon_days')->whereIn('character_id', $locked)->where(fn ($days) => $days->whereNull('reconcile_version')->orWhere('reconcile_version', '<>', $version))->update(['reconcile_version' => $version]);

            return $changed;
        });
    }

    /**
     * The ruleset and the plausibility of the session each claim sits in: the character's session that holds the end
     * of the claim's window, else the one nearest to it in time.
     *
     * @return array<int, stdClass>
     */
    private function sessionsOfClaims(int $characterId): array
    {
        $found = [];
        $rows = DB::select(
            <<<'SQL'
            select c.id, s.ruleset_id, s.counted
            from addon_claims c
            left join lateral (
                select x.ruleset_id, x.counted from sessions x
                where x.character_id = c.character_id
                order by greatest(x.start_at - to_timestamp(c.to_at), to_timestamp(c.to_at) - coalesce(x.end_at, x.start_at), interval '0 seconds'), x.session_start
                limit 1
            ) s on true
            where c.character_id = ?
            SQL,
            [$characterId],
        );
        foreach ($rows as $row) {
            $found[(int) $row->id] = $row;
        }

        return $found;
    }

    /**
     * @param  array{id: int, status: string, counted: array<string, int>, evidence: array<string, mixed>, level: int|null, zone: int|null}  $claim
     */
    private function write(array $claim, ?stdClass $session, int $version): int
    {
        $values = [
            $claim['status'],
            json_encode($claim['counted'], JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT),
            json_encode($claim['evidence'], JSON_THROW_ON_ERROR),
            $session?->ruleset_id,
            $session === null ? null : (bool) $session->counted,
            $claim['level'],
            $claim['zone'],
            $version,
        ];

        return DB::update(
            <<<'SQL'
            update addon_claims
            set status = ?, counted = ?::jsonb, evidence = ?::jsonb, ruleset_id = ?::integer, counted_ok = ?::boolean,
                level = ?::smallint, zone_id = ?::integer, reconcile_version = ?::smallint
            where id = ?
                and (status, counted, evidence, ruleset_id, counted_ok, level, zone_id, reconcile_version)
                    is distinct from (?::varchar, ?::jsonb, ?::jsonb, ?::integer, ?::boolean, ?::smallint, ?::integer, ?::smallint)
            SQL,
            [...$values, $claim['id'], ...$values],
        );
    }
}
