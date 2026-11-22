<?php

namespace App\Actions\Ingest;

use App\Support\AddonKind;
use App\Support\GatherKind;
use Illuminate\Support\Facades\DB;
use stdClass;

class ReadReconcileInput
{
    /** How far past a claim's window Scarlet's rows are read, in seconds; rule 1 looks 24 hours around a window. */
    private const int REACH = 86_400;

    private const string SCOPE_CHARACTER = 'c';

    /**
     * The JSON `scarlet-part reconcile` reads for one character and kind: the character's claims of the kind, the
     * claims of the characters in `$siblingIds` (rule 4), and Scarlet's rows of the kind around the claims' windows.
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
                $others[] = ['id' => $id, 'name' => (string) $names[$id], 'claims' => array_values(array_filter($claims[$id] ?? [], fn (array $claim): bool => $claim['scope'] === self::SCOPE_CHARACTER))];
            }
        }
        $own = $claims[$characterId] ?? [];

        return json_encode([
            'kind' => $kind->value,
            'now' => $now,
            'character' => ['id' => $characterId, 'name' => (string) ($names[$characterId] ?? ''), 'claims' => $own],
            'rows' => $kind->hasRows() ? $this->rows($characterId, $own) : [],
            'others' => $others,
        ], JSON_THROW_ON_ERROR);
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
     * Scarlet's fishing rows near the claims: each `gathers` row with the quantity of the loot window that followed
     * it (the `gather_items` rows of its session from its cast to the next one, left out when they came from opening
     * a container) and the ids of those items. Their sums are the Scarlet term of the character's totals.
     *
     * @param  list<array<string, mixed>>  $claims
     * @return list<array<string, mixed>>
     */
    private function rows(int $characterId, array $claims): array
    {
        if ($claims === []) {
            return [];
        }
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
