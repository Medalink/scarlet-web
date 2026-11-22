<?php

namespace App\Actions\Ingest;

use App\Support\AddonKind;
use App\Support\ClaimStatus;
use App\Support\GatherKind;
use Illuminate\Support\Facades\DB;
use stdClass;

class AuditClaimStatuses
{
    private const int WINDOW_SLACK = 60;

    private const string SCOPE_CHARACTER = 'c';

    private const string LIFETIME = 'life';

    private const array DATED = ['session', 'day', 'level'];

    /**
     * The checks of docs/DESIGN.md, Addon counts, Checks, that read what reconcile stored, as SQL over the stored
     * columns and never by running reconcile again: 1 every claim has a status and its amounts are its counted amounts
     * plus its stored difference, 2 counted stays between 0 and claimed and is 0 where the status says so, 3 a
     * superseded or residual claim names its evidence, 4 no Scarlet row is assigned to two claims of one origin, 5 no
     * two counted or residual dated claims of a character overlap (but a residual of rule 5 may overlap the claims
     * above it), and 11 no dated claim counts on two characters of
     * one account, nor on the one without Scarlet rows beside it while the other has them (rule 4). With no
     * character, every character is checked.
     *
     * @return list<string> one line per violation
     */
    public function handle(?int $characterId): array
    {
        return [
            ...$this->listed($this->claims('c.status is null or c.status not in ('.$this->marks(ClaimStatus::cases()).')', array_map(fn (ClaimStatus $status): string => $status->value, ClaimStatus::cases()), $characterId), 'has no valid status'),
            ...$this->listed($this->claims($this->differs(), [], $characterId), 'does not add up: its amounts are not its counted amounts plus the stored difference'),
            ...$this->listed($this->claims($this->counts(), ClaimStatus::uncounted(), $characterId), 'counts a negative amount, more than it claimed, or an amount its status does not allow'),
            ...$this->listed($this->claims("c.status in (?, ?) and not (jsonb_exists(c.evidence, 'with') or coalesce((c.evidence #>> '{rows,n}')::int, 0) > 0)", [ClaimStatus::Superseded->value, ClaimStatus::Residual->value], $characterId), 'is superseded or residual and names no evidence'),
            ...$this->sharedRows($characterId),
            ...$this->overlaps($characterId),
            ...$this->twins($characterId),
            ...$this->twinsWithoutRows($characterId),
        ];
    }

    private function differs(): string
    {
        return <<<'SQL'
            c.status is not null and (c.counted is null or c.evidence is null or exists (
                select 1 from jsonb_each(c.amounts) a(metric, value)
                where (a.value #>> '{}')::numeric is distinct from coalesce((c.counted ->> a.metric)::numeric, 0) + coalesce((c.evidence #>> array['diff', a.metric])::numeric, 0)
            ))
            SQL;
    }

    private function counts(): string
    {
        $statuses = $this->marks(ClaimStatus::uncounted());
        $rejected = ClaimStatus::Rejected->value;

        return <<<SQL
            c.status is not null and c.counted is not null and exists (
                select 1 from jsonb_each(c.counted) a(metric, value)
                where (a.value #>> '{}')::numeric < 0
                    or (c.status <> '{$rejected}' and (a.value #>> '{}')::numeric > coalesce((c.amounts ->> a.metric)::numeric, 0))
                    or (c.status in ({$statuses}) and (a.value #>> '{}')::numeric <> 0)
            )
            SQL;
    }

    /** @param  list<mixed>  $items */
    private function marks(array $items): string
    {
        return implode(', ', array_fill(0, count($items), '?'));
    }

    /**
     * @param  list<string>  $bindings  the values of the `?` in `$where`, before the two character ids
     * @return list<stdClass>
     */
    private function claims(string $where, array $bindings, ?int $characterId): array
    {
        $sql = str_replace('{where}', $where, <<<'SQL'
            select c.id, ch.name as character_name, c.origin, c.kind, c.window, c.to_at, count(*) over () as total
            from addon_claims c join characters ch on ch.id = c.character_id
            where ({where}) and (?::bigint is null or c.character_id = ?)
            order by c.id
            limit 20
            SQL);

        return $this->rows($sql, [...$bindings, $characterId, $characterId]);
    }

    /** @return list<string> */
    private function sharedRows(?int $characterId): array
    {
        $kind = AddonKind::Fishing->value;
        $fish = GatherKind::Fish->value;
        $lines = [];
        foreach ($this->rows(
            <<<'SQL'
            with assigned as (
                select c.id, c.character_id, c.origin, c.kind, c.from_at, c.to_at,
                    (c.evidence #>> '{rows,first}')::bigint as first_at, (c.evidence #>> '{rows,last}')::bigint as last_at, (c.evidence #>> '{rows,n}')::int as n
                from addon_claims c
                where c.kind = ? and coalesce((c.evidence #>> '{rows,n}')::int, 0) > 0 and (?::bigint is null or c.character_id = ?)
            ), ordered as (
                select a.*, ch.name as character_name,
                    lag(a.last_at) over (partition by a.character_id, a.origin order by a.first_at, a.id) as before_last,
                    lag(a.id) over (partition by a.character_id, a.origin order by a.first_at, a.id) as before_id,
                    (select count(*) from gathers g where g.character_id = a.character_id and g.kind = ?
                        and g.at between to_timestamp(a.first_at / 1000.0) - interval '1 millisecond' and to_timestamp(a.last_at / 1000.0) + interval '1 millisecond') as held
                from assigned a join characters ch on ch.id = a.character_id
            )
            select id, character_name, origin, before_id,
                case when before_last is not null and first_at <= before_last then 'shares rows with claim ' || before_id
                    when held < n then 'was assigned ' || n || ' rows but Scarlet holds only ' || held || ' inside the span of them'
                    when first_at < (coalesce(from_at, to_at) - ?) * 1000 or last_at > (to_at + ?) * 1000 then 'was assigned rows more than 60 seconds outside its window'
                end as problem
            from ordered
            order by id
            SQL,
            [$kind, $characterId, $characterId, $fish, self::WINDOW_SLACK, self::WINDOW_SLACK],
        ) as $row) {
            if ($row->problem !== null) {
                $lines[] = "claim {$row->id} of {$row->character_name} ({$row->origin}) {$row->problem}";
            }
        }
        foreach ($this->rows(
            <<<'SQL'
            select ch.name as character_name, c.origin, sum((c.evidence #>> '{rows,n}')::int) as assigned,
                (select count(*) from gathers g where g.character_id = c.character_id and g.kind = ?) as scarlet
            from addon_claims c join characters ch on ch.id = c.character_id
            where c.kind = ? and (?::bigint is null or c.character_id = ?)
            group by c.character_id, ch.name, c.origin
            having sum((c.evidence #>> '{rows,n}')::int) > (select count(*) from gathers g where g.character_id = c.character_id and g.kind = ?)
            SQL,
            [$fish, $kind, $characterId, $characterId, $fish],
        ) as $row) {
            $lines[] = "{$row->character_name}: claims of {$row->origin} were assigned {$row->assigned} Scarlet rows and Scarlet holds {$row->scarlet}";
        }

        return $lines;
    }

    /** @return list<string> */
    private function overlaps(?int $characterId): array
    {
        $adding = ClaimStatus::adding();
        $lines = [];
        foreach ($this->rows(
            <<<'SQL'
            select ch.name as character_name, a.id as first_id, b.id as second_id, a.origin as first_origin, b.origin as second_origin
            from addon_claims a
            join addon_claims b on b.character_id = a.character_id and b.id > a.id and b.kind = a.kind
            join characters ch on ch.id = a.character_id
            where a.status in (?, ?) and b.status in (?, ?) and a.window in (?, ?, ?) and b.window in (?, ?, ?)
                and (?::bigint is null or a.character_id = ?)
                and (
                    (a.origin = b.origin and (a.to_at = b.to_at or (coalesce(a.from_at, a.to_at) < b.to_at and coalesce(b.from_at, b.to_at) < a.to_at)))
                    or (a.origin <> b.origin and least(a.to_at, b.to_at) - greatest(coalesce(a.from_at, a.to_at), coalesce(b.from_at, b.to_at)) > ?
                        and not (a.status = ? and a.evidence ->> 'rule' = '5') and not (b.status = ? and b.evidence ->> 'rule' = '5'))
                )
            order by a.id, b.id
            limit 20
            SQL,
            [...$adding, ...$adding, ...self::DATED, ...self::DATED, $characterId, $characterId, self::WINDOW_SLACK, ClaimStatus::Residual->value, ClaimStatus::Residual->value],
        ) as $row) {
            $lines[] = "claims {$row->first_id} ({$row->first_origin}) and {$row->second_id} ({$row->second_origin}) of {$row->character_name} both count and overlap";
        }

        return $lines;
    }

    /** @return list<string> */
    private function twins(?int $characterId): array
    {
        $adding = ClaimStatus::adding();
        $lines = [];
        foreach ($this->rows(
            <<<'SQL'
            select a.id as first_id, b.id as second_id, ca.name as first_name, cb.name as second_name, a.origin, a.kind, a.to_at
            from addon_claims a
            join addon_claims b on b.id > a.id and b.character_id <> a.character_id and b.origin = a.origin and b.kind = a.kind and b.window = a.window
                and b.from_at is not distinct from a.from_at and b.to_at = a.to_at and b.amounts = a.amounts and b.dims = a.dims
            join characters ca on ca.id = a.character_id
            join characters cb on cb.id = b.character_id
            where a.status in (?, ?) and b.status in (?, ?) and a.scope = ? and b.scope = ? and a.window <> ?
                and (?::bigint is null or a.character_id = ? or b.character_id = ?)
                and (
                    (ca.account_id is not null and ca.account_id = cb.account_id)
                    or exists (select 1 from install_character x join install_character y on y.install_id = x.install_id where x.character_id = ca.id and y.character_id = cb.id)
                )
            order by a.id, b.id
            limit 20
            SQL,
            [...$adding, ...$adding, self::SCOPE_CHARACTER, self::SCOPE_CHARACTER, self::LIFETIME, $characterId, $characterId, $characterId],
        ) as $row) {
            $lines[] = "claims {$row->first_id} of {$row->first_name} and {$row->second_id} of {$row->second_name} are one record ({$row->origin} {$row->kind} to {$row->to_at}) and both count";
        }

        return $lines;
    }

    /** @return list<string> */
    private function twinsWithoutRows(?int $characterId): array
    {
        $slack = self::WINDOW_SLACK;
        $lines = [];
        foreach ($this->rows(
            <<<'SQL'
            select s.id as loser_id, w.id as winner_id, cs.name as loser_name, cw.name as winner_name, s.origin, s.kind, s.to_at
            from addon_claims s
            join characters cs on cs.id = s.character_id
            join characters cw on cw.name = s.evidence #>> '{with,character}'
            join addon_claims w on w.character_id = cw.id and w.origin = s.origin and w.kind = s.kind and w.window = s.window and w.scope = s.scope
                and w.from_at is not distinct from s.from_at and w.to_at = s.to_at and w.amounts = s.amounts and w.dims = s.dims
            where s.status = ? and s.evidence ->> 'rule' = '4' and s.kind = ?
                and (?::bigint is null or s.character_id = ? or cw.id = ?)
                and exists (
                    select 1 from gathers g where g.character_id = s.character_id and g.kind = ?
                        and g.at between to_timestamp(coalesce(s.from_at, s.to_at) - ?::bigint) and to_timestamp(s.to_at + ?::bigint)
                )
                and not exists (
                    select 1 from gathers g where g.character_id = w.character_id and g.kind = ?
                        and g.at between to_timestamp(coalesce(w.from_at, w.to_at) - ?::bigint) and to_timestamp(w.to_at + ?::bigint)
                )
            order by s.id
            limit 20
            SQL,
            [ClaimStatus::Superseded->value, AddonKind::Fishing->value, $characterId, $characterId, $characterId, GatherKind::Fish->value, self::WINDOW_SLACK, self::WINDOW_SLACK, GatherKind::Fish->value, self::WINDOW_SLACK, self::WINDOW_SLACK],
        ) as $row) {
            $lines[] = "claim {$row->loser_id} of {$row->loser_name} is superseded by claim {$row->winner_id} of {$row->winner_name}, which has no Scarlet row within {$slack} s of the record ({$row->origin} {$row->kind} to {$row->to_at}) while {$row->loser_name} has";
        }

        return $lines;
    }

    /**
     * @param  list<stdClass>  $claims
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
     * @param  list<int|string|null>  $bindings
     * @return list<stdClass>
     */
    private function rows(string $sql, array $bindings): array
    {
        /** @var list<stdClass> */
        return array_values(DB::select($sql, $bindings));
    }
}
