<?php

namespace App\Actions\Ingest;

use App\Models\Character;
use App\Support\AddonAuditReport;
use Illuminate\Support\Facades\DB;
use stdClass;

class AuditAddonClaims
{
    public function __construct(
        private readonly AuditClaimStatuses $statuses,
        private readonly AuditCountClaims $counts,
        private readonly AuditClaimNotes $notes,
        private readonly AuditAccountCeilings $ceilings,
        private readonly ExplainAddonClaims $explain,
    ) {}

    /**
     * Check the stored addon claims (docs/DESIGN.md, Addon counts, Checks): every claim has a carrying part and takes the
     * amounts of the one whose id sorts highest, no character holds one key twice, no text field holds a `First-Last`
     * name but the character's own, and the status checks 1 to 5 and 11 hold over what reconcile stored. Parts that
     * disagree on a claim's amounts, a claim assigned more looted rows than it claims landed and the account ceilings of
     * check 8 are notes. The report also carries the table that explains each total. With no character, every
     * character is checked.
     */
    public function handle(?Character $character = null): AddonAuditReport
    {
        $id = $character?->id;

        return new AddonAuditReport(
            $this->totals($id),
            [
                ...$this->listed($this->claimsWithNoPart($id), 'has no carrying part'),
                ...$this->listed($this->claimsWithStaleAmounts($id), 'does not take the amounts of its highest part'),
                ...$this->duplicateKeys($id),
                ...$this->listed($this->claimsNamingAPlayer($id), 'holds a player name in a text field'),
                ...$this->statuses->handle($id),
                ...$this->counts->handle($id),
            ],
            [
                ...$this->listed($this->claimsWithDisagreeingParts($id), 'has parts that disagree on its amounts; the highest part id wins'),
                ...$this->notes->handle($id),
                ...$this->ceilings->handle($id),
            ],
            $this->explain->handle($id),
        );
    }

    /** @return list<array{character: string, scope: string, kind: string, claims: int, amounts: array<string, int|float>}> */
    private function totals(?int $characterId): array
    {
        /** @var array<string, array{character: string, scope: string, kind: string, claims: int}> $claims */
        $claims = [];
        /** @var array<string, array<string, int|float>> $amounts */
        $amounts = [];
        foreach ($this->rows(<<<'SQL'
            select ch.name as character_name, c.scope, c.kind, count(*) as claims
            from addon_claims c join characters ch on ch.id = c.character_id
            where (?::bigint is null or c.character_id = ?)
            group by ch.name, c.scope, c.kind
            order by ch.name, c.scope, c.kind
            SQL, $characterId) as $row) {
            $claims[$row->character_name.'|'.$row->scope.'|'.$row->kind] = ['character' => (string) $row->character_name, 'scope' => (string) $row->scope, 'kind' => (string) $row->kind, 'claims' => (int) $row->claims];
        }
        foreach ($this->rows(<<<'SQL'
            select ch.name as character_name, c.scope, c.kind, a.metric,
                sum(case when jsonb_typeof(a.value) = 'number' then (a.value #>> '{}')::numeric end) as total
            from addon_claims c
            join characters ch on ch.id = c.character_id
            cross join lateral jsonb_each(c.amounts) as a(metric, value)
            where (?::bigint is null or c.character_id = ?)
            group by ch.name, c.scope, c.kind, a.metric
            order by a.metric
            SQL, $characterId) as $row) {
            $total = (string) $row->total;
            $amounts[$row->character_name.'|'.$row->scope.'|'.$row->kind][(string) $row->metric] = str_contains($total, '.') ? (float) $total : (int) $total;
        }

        $totals = [];
        foreach ($claims as $key => $total) {
            $totals[] = $total + ['amounts' => $amounts[$key] ?? []];
        }

        return $totals;
    }

    /** @return list<stdClass> */
    private function claimsWithNoPart(?int $characterId): array
    {
        return $this->rows(<<<'SQL'
            select c.id, ch.name as character_name, c.origin, c.kind, c.window, c.to_at, count(*) over () as total
            from addon_claims c join characters ch on ch.id = c.character_id
            where not exists (select 1 from addon_claim_parts p where p.claim_id = c.id)
                and (?::bigint is null or c.character_id = ?)
            order by c.id
            limit 20
            SQL, $characterId);
    }

    /** @return list<stdClass> */
    private function claimsWithStaleAmounts(?int $characterId): array
    {
        return $this->rows(<<<'SQL'
            select c.id, ch.name as character_name, c.origin, c.kind, c.window, c.to_at, count(*) over () as total
            from addon_claims c
            join characters ch on ch.id = c.character_id
            join lateral (
                select p.amounts, p.from_at from addon_claim_parts p where p.claim_id = c.id order by p.part_id desc limit 1
            ) highest on true
            where (c.amounts is distinct from highest.amounts or c.from_at is distinct from highest.from_at)
                and (?::bigint is null or c.character_id = ?)
            order by c.id
            limit 20
            SQL, $characterId);
    }

    /** @return list<stdClass> */
    private function claimsNamingAPlayer(?int $characterId): array
    {
        return $this->rows(<<<'SQL'
            select c.id, ch.name as character_name, c.origin, c.kind, c.window, c.to_at, count(*) over () as total
            from addon_claims c
            join characters ch on ch.id = c.character_id
            where exists (
                select 1
                from regexp_matches(concat_ws(' ', c.origin, c.store, c.window, c.kind, c.dims::text, c.amounts::text), '\m[A-Z][a-z'']+-[A-Z][a-z'']+\M', 'g') as found(name)
                where lower((found.name)[1]) <> lower(ch.name)
            )
                and (?::bigint is null or c.character_id = ?)
            order by c.id
            limit 20
            SQL, $characterId);
    }

    /** @return list<stdClass> */
    private function claimsWithDisagreeingParts(?int $characterId): array
    {
        return $this->rows(<<<'SQL'
            select c.id, ch.name as character_name, c.origin, c.kind, c.window, c.to_at, count(*) over () as total
            from addon_claims c join characters ch on ch.id = c.character_id
            where (select count(distinct p.amounts) from addon_claim_parts p where p.claim_id = c.id) > 1
                and (?::bigint is null or c.character_id = ?)
            order by c.id
            limit 20
            SQL, $characterId);
    }

    /** @return list<string> */
    private function duplicateKeys(?int $characterId): array
    {
        $lines = [];
        foreach ($this->rows(<<<'SQL'
            select ch.name as character_name, c.origin, c.store, c.scope, c.window, c.kind, c.dims::text as dims, c.to_at, c.key_from, count(*) as copies
            from addon_claims c join characters ch on ch.id = c.character_id
            where (?::bigint is null or c.character_id = ?)
            group by c.character_id, ch.name, c.origin, c.store, c.scope, c.window, c.kind, c.dims, c.to_at, c.key_from
            having count(*) > 1
            order by ch.name, c.to_at
            limit 20
            SQL, $characterId) as $row) {
            $lines[] = "{$row->character_name} holds {$row->copies} claims of one key: {$row->origin} '{$row->store}' {$row->scope} {$row->window} {$row->kind} {$row->dims} to {$row->to_at} from {$row->key_from}";
        }

        return $lines;
    }

    /**
     * @param  list<stdClass>  $claims  rows with the id, character, origin, kind, window and end of a claim and the number of claims that match
     * @return list<string>
     */
    private function listed(array $claims, string $what): array
    {
        $lines = array_map(fn (stdClass $claim): string => "claim {$claim->id} of {$claim->character_name} ({$claim->origin} {$claim->kind} {$claim->window} to {$claim->to_at}) {$what}", $claims);
        $total = $claims === [] ? 0 : (int) $claims[0]->total;
        if ($total > count($claims)) {
            $lines[] = 'and '.($total - count($claims))." more that {$what}";
        }

        return $lines;
    }

    /**
     * @param  string  $sql  a query with two `?` placeholders for the character id
     * @return list<stdClass>
     */
    private function rows(string $sql, ?int $characterId): array
    {
        /** @var list<stdClass> */
        return array_values(DB::select($sql, [$characterId, $characterId]));
    }
}
