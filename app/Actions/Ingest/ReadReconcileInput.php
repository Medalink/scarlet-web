<?php

namespace App\Actions\Ingest;

use App\Actions\Public\QuestRecord;
use App\Support\AddonKind;
use App\Support\GatherKind;
use App\Support\JoinedDungeonRuns;
use App\Support\SharingGroup;
use App\Support\XpSource;
use Illuminate\Support\Facades\DB;
use stdClass;

class ReadReconcileInput
{
    /** How far past a claim's window Scarlet's rows are read, in seconds; rule 1 looks 24 hours around a window. */
    private const int REACH = 86_400;

    /**
     * How far past a claim's window another character's rows are read for rule 4, in seconds: a second more than the
     * minute the rule counts in, so that the part's own bounds in milliseconds are the only filter on either side.
     */
    private const int TWIN_REACH = 61;

    /**
     * How far from a boss claim's time its Scarlet rows and sessions are read, in seconds: the minute the rule
     * searches for a pair, the 2 seconds it matches within after the offset, and a second of rounding.
     */
    private const int BOSS_REACH = 63;

    private const string SCOPE_CHARACTER = 'c';

    /** The largest whole number a dimension can hold and still bind as an `integer`; the rule holds a larger one as `unmapped`. */
    private const int LARGEST_DIMENSION = 2_147_483_647;

    /**
     * The JSON `scarlet-part reconcile` reads for one character and kind: the character's claims of the kind, the
     * claims of the characters in `$siblingIds` (rule 4) with the times of the rows each holds beside its claims, and
     * Scarlet's rows of the kind around the claims' windows.
     * The same database state always gives the same text, so it also fingerprints what a reconcile saw.
     *
     * @param  list<int>  $siblingIds  every character that is reconciled together, the character among them
     */
    public function handle(int $characterId, AddonKind $kind, array $siblingIds, int $now): string
    {
        $claims = $this->claims($siblingIds, $kind);
        $names = DB::table('characters')->whereIn('id', $siblingIds)->pluck('name', 'id');
        $others = [];
        foreach ($siblingIds as $id) {
            if ($id !== $characterId) {
                $theirs = array_values(array_filter($claims[$id] ?? [], fn (array $claim): bool => $claim['scope'] === self::SCOPE_CHARACTER));
                $others[] = [
                    'id' => $id,
                    'name' => (string) $names[$id],
                    'claims' => $theirs,
                    'rows' => match (true) {
                        $kind === AddonKind::Fishing => $this->rowsBeside($id, $kind),
                        $kind->hasRows() => $this->rowsOf($id, $kind, $theirs),
                        default => [],
                    },
                ] + $this->sessionSpans($id, $kind, $theirs);
            }
        }
        $own = $claims[$characterId] ?? [];

        return json_encode([
            'kind' => $kind->value,
            'now' => $now,
            'character' => ['id' => $characterId, 'name' => (string) ($names[$characterId] ?? ''), 'claims' => $own] + $this->sessionSpans($characterId, $kind, $own),
            'rows' => $kind->hasRows() ? $this->rowsOf($characterId, $kind, $own) : [],
            'others' => $others,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * The `spans` of a character for the kinds whose rule reads them: the first and last time, in epoch milliseconds,
     * of each of its sessions with a combat part that lies near one of the character's claims. Left out for the others,
     * so their input text stays as it was.
     *
     * @param  list<array<string, mixed>>  $claims
     * @return array{spans?: list<array{int, int}>}
     */
    private function sessionSpans(int $characterId, AddonKind $kind, array $claims): array
    {
        if ($kind !== AddonKind::Boss) {
            return [];
        }
        if ($claims === []) {
            return ['spans' => []];
        }

        return ['spans' => array_values(array_map(fn (stdClass $row): array => [(int) $row->start_ms, (int) $row->end_ms], DB::select(
            <<<'SQL'
            select round(extract(epoch from s.start_at) * 1000)::bigint as start_ms, round(extract(epoch from s.end_at) * 1000)::bigint as end_ms
            from sessions s
            where s.character_id = ? and jsonb_exists(s.groups, ?)
                and exists (
                    select 1 from addon_claims c
                    where c.character_id = s.character_id and c.kind = ? and c.scope = ? and c.window <> 'life'
                        and c.to_at between extract(epoch from s.start_at) - ?::bigint and extract(epoch from s.end_at) + ?::bigint
                )
            order by s.start_at, s.id
            SQL,
            [$characterId, SharingGroup::COMBAT, $kind->value, self::SCOPE_CHARACTER, self::BOSS_REACH, self::BOSS_REACH],
        )))];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, list<array<string, mixed>>>
     */
    private function claims(array $ids, AddonKind $kind): array
    {
        $claims = [];
        $rows = DB::table('addon_claims')->whereIn('character_id', $ids)->where('kind', $kind->value)
            ->orderBy('character_id')->orderBy('origin')->orderBy('store')->orderBy('to_at')->orderBy('id')
            ->get(['id', 'character_id', 'origin', 'store', 'scope', 'window', 'kind', 'from_at', 'to_at', 'dims', 'amounts']);
        foreach ($rows as $row) {
            $claims[(int) $row->character_id][] = [
                'id' => (int) $row->id,
                'origin' => (string) $row->origin,
                'store' => (string) $row->store,
                'scope' => (string) $row->scope,
                'window' => (string) $row->window,
                'kind' => (string) $row->kind,
                'from' => $row->from_at === null ? null : (int) $row->from_at,
                'to' => (int) $row->to_at,
                'dims' => json_decode((string) $row->dims, false, 512, JSON_THROW_ON_ERROR),
                'amounts' => json_decode((string) $row->amounts, false, 512, JSON_THROW_ON_ERROR),
            ];
        }

        return $claims;
    }

    /**
     * The times, in epoch milliseconds, of every one of the character's fish `gathers` rows within a minute and a
     * second of the window of one of its dated claims of the kind, once each: what rule 4 counts between the
     * characters that hold one record, the same way it counts the rows of the character itself.
     *
     * @return list<int>
     */
    private function rowsBeside(int $characterId, AddonKind $kind): array
    {
        $rows = DB::select(
            <<<'SQL'
            select round(extract(epoch from g.at) * 1000)::bigint as at_ms
            from gathers g
            where g.character_id = ? and g.kind = ?
                and exists (
                    select 1 from addon_claims c
                    where c.character_id = g.character_id and c.kind = ? and c.scope = ? and c.window <> 'life'
                        and g.at between to_timestamp(coalesce(c.from_at, c.to_at) - ?::bigint) and to_timestamp(c.to_at + ?::bigint)
                )
            order by at_ms
            SQL,
            [$characterId, GatherKind::Fish->value, $kind->value, self::SCOPE_CHARACTER, self::TWIN_REACH, self::TWIN_REACH],
        );

        return array_values(array_map(fn (stdClass $row): int => (int) $row->at_ms, $rows));
    }

    /**
     * Scarlet's rows of the kind that its rule reads for the claims (docs/DESIGN.md, Addon counts, Reconcile).
     *
     * @param  list<array<string, mixed>>  $claims
     * @return list<array<string, mixed>>
     */
    private function rowsOf(int $characterId, AddonKind $kind, array $claims): array
    {
        if ($claims === []) {
            return [];
        }

        if ($kind === AddonKind::Kill) {
            return $this->killRows($characterId, $claims);
        }
        if ($kind === AddonKind::Xp) {
            return $this->questRows($characterId, $claims);
        }
        if ($kind === AddonKind::Run) {
            return $this->runRows($characterId);
        }
        if ($kind === AddonKind::Boss) {
            return $this->bossRows($characterId);
        }

        return $this->fishRows($characterId, $claims);
    }

    /**
     * Scarlet's boss kills near the claims: each `boss_kills` row of the character within {@see self::BOSS_REACH} seconds
     * of a dated claim of the kind, counted or not, with its encounter id (null when the line had none) and session.
     *
     * @return list<array<string, mixed>>
     */
    private function bossRows(int $characterId): array
    {
        return array_values(array_map(fn (stdClass $row): array => [
            'at' => (int) $row->at_ms,
            'key' => $row->encounter_id === null ? null : (int) $row->encounter_id,
            'session' => (int) $row->session_start,
        ], DB::select(
            <<<'SQL'
            select round(extract(epoch from k.at) * 1000)::bigint as at_ms, k.encounter_id, k.session_start
            from boss_kills k
            where k.character_id = ?
                and exists (
                    select 1 from addon_claims c
                    where c.character_id = k.character_id and c.kind = ? and c.scope = ? and c.window <> 'life'
                        and k.at between to_timestamp(c.to_at - ?::bigint) and to_timestamp(c.to_at + ?::bigint)
                )
            order by k.at, k.encounter_id nulls first, k.session_start
            SQL,
            [$characterId, AddonKind::Boss->value, self::SCOPE_CHARACTER, self::BOSS_REACH, self::BOSS_REACH],
        )));
    }

    /**
     * The whole numbers one dimension of the claims holds, written the way the rule reads them.
     *
     * @param  list<array<string, mixed>>  $claims
     */
    private function dimension(array $claims, string $key): string
    {
        $values = [];
        foreach ($claims as $claim) {
            $value = ((array) $claim['dims'])[$key] ?? null;
            if (is_string($value) && ctype_digit($value) && (string) (int) $value === $value && (int) $value <= self::LARGEST_DIMENSION) {
                $values[(int) $value] = (int) $value;
            }
        }
        sort($values);

        return '{'.implode(',', $values).'}';
    }

    /**
     * Scarlet's kills as one row per level the claims name and one for the kills with no level, which the decoder
     * writes until a session has one: the count of the character's `kills` rows that are not players, counted or
     * not, and the time of the last of them.
     *
     * @param  list<array<string, mixed>>  $claims
     * @return list<array<string, mixed>>
     */
    private function killRows(int $characterId, array $claims): array
    {
        return array_values(array_map(fn (stdClass $row): array => [
            'at' => (int) $row->at_ms,
            'level' => $row->level === null ? null : (int) $row->level,
            'count' => (int) $row->kills,
        ], DB::select(
            <<<'SQL'
            select level, count(*) as kills, round(extract(epoch from max(at)) * 1000)::bigint as at_ms
            from kills
            where character_id = ? and not player and (level is null or level = any(?::int[]))
            group by level
            order by level nulls first
            SQL,
            [$characterId, $this->dimension($claims, 'level')],
        )));
    }

    /**
     * Scarlet's quest turn-ins from both tables that hold them, because `quests` and `xp` are separate sharing
     * groups: the `quest_events` rows turned in and the `xp_gains` rows of source quest, each for the quests the claims
     * name and with no quest id (null when the client hid it).
     *
     * @param  list<array<string, mixed>>  $claims
     * @return list<array<string, mixed>>
     */
    private function questRows(int $characterId, array $claims): array
    {
        $quests = $this->dimension($claims, 'quest');

        return array_values(array_map(fn (stdClass $row): array => [
            'at' => (int) $row->at_ms,
            'key' => $row->quest === null ? null : (int) $row->quest,
            'xp' => (int) $row->xp,
            'level' => $row->level === null ? null : (int) $row->level,
            'zone' => $row->zone_id === null ? null : (int) $row->zone_id,
            'events' => (bool) $row->events,
        ], DB::select(
            <<<'SQL'
            select round(extract(epoch from at) * 1000)::bigint as at_ms, quest_id as quest, coalesce(xp, 0) as xp, level, zone_id, true as events
            from quest_events
            where character_id = ? and event = ? and (quest_id is null or quest_id = any(?::int[]))
            union all
            select round(extract(epoch from at) * 1000)::bigint as at_ms, source_id as quest, amount as xp, level, zone_id, false as events
            from xp_gains
            where character_id = ? and source = ? and (source_id is null or source_id = any(?::int[]))
            order by at_ms, events desc, quest nulls first, xp
            SQL,
            [$characterId, QuestRecord::TURNED_IN, $quests, $characterId, XpSource::Quest->value, $quests],
        )));
    }

    /**
     * Scarlet's dungeon runs joined along `continues_previous`, each with its instance, first entry and last
     * leave: every run of the character, because a run of any instance beside a claim is the run it was in.
     *
     * @return list<array<string, mixed>>
     */
    private function runRows(int $characterId): array
    {
        return array_values(array_map(fn (stdClass $row): array => [
            'at' => (int) $row->at_ms,
            'end' => (int) $row->end_ms,
            'key' => (int) $row->instance_id,
            'level' => $row->level === null ? null : (int) $row->level,
            'zone' => $row->zone_id === null ? null : (int) $row->zone_id,
        ], DB::select(
            'with '.JoinedDungeonRuns::cte('r.character_id = ?').<<<'SQL'

            select round(extract(epoch from at) * 1000)::bigint as at_ms, round(extract(epoch from end_at) * 1000)::bigint as end_ms,
                instance_id, level, zone_id
            from joined
            order by at, instance_id
            SQL,
            [$characterId],
        )));
    }

    /**
     * Scarlet's fishing rows near the claims: each `gathers` row with the quantity of the loot window that followed
     * it (the `gather_items` rows of its session from its cast to the next one, left out when they came from opening
     * a container) and the ids of those items. Their sums are the Scarlet term of the character's totals.
     *
     * @param  non-empty-list<array<string, mixed>>  $claims
     * @return list<array<string, mixed>>
     */
    private function fishRows(int $characterId, array $claims): array
    {
        $from = min(array_map(fn (array $claim): int => (int) ($claim['from'] ?? $claim['to']), $claims)) - self::REACH;
        $until = max(array_map(fn (array $claim): int => (int) $claim['to'], $claims)) + self::REACH;
        $fish = GatherKind::Fish->value;

        return array_values(array_map(fn (stdClass $row): array => [
            'at' => (int) $row->at_ms,
            'looted' => (bool) $row->looted,
            'items' => (int) $row->items,
            'level' => $row->level === null ? null : (int) $row->level,
            'zone' => $row->zone_id === null ? null : (int) $row->zone_id,
            'session' => (int) $row->session_start,
            'item_ids' => array_map('intval', json_decode((string) $row->item_ids, true, 512, JSON_THROW_ON_ERROR)),
        ], DB::select(
            <<<'SQL'
            with fish as (
                select at, looted, session_start, level, zone_id,
                    lead(at) over (partition by session_start order by at) as next_at
                from gathers
                where character_id = ? and kind = ? and at between to_timestamp(?) and to_timestamp(?)
            )
            select round(extract(epoch from fish.at) * 1000)::bigint as at_ms, fish.looted, fish.session_start, fish.level, fish.zone_id,
                coalesce((
                    select sum(i.quantity) from gather_items i
                    where i.character_id = ? and i.session_start = fish.session_start and i.kind = ? and i.container_item_id is null
                        and i.at >= fish.at and (fish.next_at is null or i.at < fish.next_at)
                ), 0) as items,
                coalesce((
                    select jsonb_agg(distinct i.item_id) from gather_items i
                    where i.character_id = ? and i.session_start = fish.session_start and i.kind = ? and i.container_item_id is null
                        and i.at >= fish.at and (fish.next_at is null or i.at < fish.next_at)
                ), '[]'::jsonb) as item_ids
            from fish
            order by fish.at
            SQL,
            [$characterId, $fish, $from, $until, $characterId, $fish, $characterId, $fish],
        )));
    }
}
