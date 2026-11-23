<?php

namespace App\Actions\Ingest;

use App\Actions\Public\QuestRecord;
use App\Support\AddonKind;
use App\Support\ClaimStatus;
use App\Support\JoinedDungeonRuns;
use App\Support\XpSource;
use Illuminate\Support\Facades\DB;
use stdClass;

class AuditCountClaims
{
    /** Seconds before a claim's start and after its end in which a joined Scarlet run of its instance is the claim's run. */
    private const int RUN_BEFORE = 600;

    private const int RUN_AFTER = 5;

    /** Seconds under which two claims of one instance are one run. */
    private const int RUN_JOIN = 2_400;

    private const string SCOPE_CHARACTER = 'c';

    /**
     * Checks 4 and 11 of docs/DESIGN.md, Addon counts, Checks, for the kinds that count by level, quest and run, as
     * SQL over the stored columns and Scarlet's own rows. Kill: no two claims of one origin and store that count, or
     * are superseded by rows, share a level, and the count assigned is Scarlet's kills at the level plus the ones with
     * no level. Xp: per character and quest id the counted claims number at most the claims minus Scarlet's turn-ins,
     * and no Scarlet row is the evidence of two claims. Run: no counted claim has a joined Scarlet run beside it, and
     * no two counted claims of one instance are under 2,400 seconds apart. Every kind: no claim is superseded by rule 4
     * in favour of a character that holds fewer Scarlet rows beside it. With no character, every character is checked.
     *
     * @return list<string> one line per violation
     */
    public function handle(?int $characterId): array
    {
        return [
            ...$this->sharedLevels($characterId),
            ...$this->killsAssigned($characterId),
            ...$this->questsCounted($characterId),
            ...$this->questRowsShared($characterId),
            ...$this->runsBesideRows($characterId),
            ...$this->runsApart($characterId),
            ...$this->twinsOnFewerRows(AddonKind::Kill, $characterId),
            ...$this->twinsOnFewerRows(AddonKind::Xp, $characterId),
            ...$this->twinsOnFewerRows(AddonKind::Run, $characterId),
        ];
    }

    /** @return list<string> */
    private function sharedLevels(?int $characterId): array
    {
        $lines = [];
        foreach ($this->rows(
            <<<'SQL'
            select ch.name as character_name, c.origin, c.store, c.level, count(*) as claims
            from addon_claims c join characters ch on ch.id = c.character_id
            where c.kind = ? and c.scope = ? and c.level is not null
                and (c.status in (?, ?) or (c.status = ? and c.evidence ->> 'rule' = '3' and coalesce((c.evidence #>> '{rows,n}')::int, 0) > 0))
                and (?::bigint is null or c.character_id = ?)
            group by c.character_id, ch.name, c.origin, c.store, c.level
            having count(*) > 1
            order by ch.name, c.level
            limit 20
            SQL,
            [AddonKind::Kill->value, self::SCOPE_CHARACTER, ClaimStatus::Counted->value, ClaimStatus::Residual->value, ClaimStatus::Superseded->value, $characterId, $characterId],
        ) as $row) {
            $lines[] = "{$row->character_name} holds {$row->claims} kill claims of {$row->origin} '{$row->store}' that count or were superseded by rows at level {$row->level}";
        }

        return $lines;
    }

    /** @return list<string> */
    private function killsAssigned(?int $characterId): array
    {
        $lines = [];
        foreach ($this->rows(
            <<<'SQL'
            select c.id, ch.name as character_name, c.origin, c.level, (c.evidence #>> '{rows,n}')::bigint as assigned, held.kills
            from addon_claims c
            join characters ch on ch.id = c.character_id
            cross join lateral (
                select count(*) as kills from kills k
                where k.character_id = c.character_id and not k.player and (k.level = c.level or k.level is null)
            ) held
            where c.kind = ? and c.status in (?, ?, ?) and c.evidence ->> 'rule' = '3' and jsonb_exists(c.evidence, 'rows') and c.level is not null
                and (c.evidence #>> '{rows,n}')::bigint <> held.kills
                and (?::bigint is null or c.character_id = ?)
            order by c.id
            limit 20
            SQL,
            [AddonKind::Kill->value, ClaimStatus::Counted->value, ClaimStatus::Residual->value, ClaimStatus::Superseded->value, $characterId, $characterId],
        ) as $row) {
            $lines[] = "claim {$row->id} of {$row->character_name} ({$row->origin} level {$row->level}) was assigned {$row->assigned} Scarlet kills and Scarlet holds {$row->kills} at the level and with none";
        }

        return $lines;
    }

    /** @return list<string> */
    private function questsCounted(?int $characterId): array
    {
        $lines = [];
        foreach ($this->rows(
            <<<'SQL'
            with grouped as (
                select c.character_id, (c.dims ->> 'quest')::int as quest, count(*) as claims, count(*) filter (where c.status = ?) as counted
                from addon_claims c
                where c.kind = ? and c.evidence ->> 'rule' = '2' and (?::bigint is null or c.character_id = ?)
                group by c.character_id, (c.dims ->> 'quest')::int
            )
            select ch.name as character_name, g.quest, g.claims, g.counted, held.turned_in
            from grouped g
            join characters ch on ch.id = g.character_id
            cross join lateral (
                select greatest(
                    (select count(*) from quest_events q where q.character_id = g.character_id and q.event = ? and q.quest_id = g.quest),
                    (select count(*) from xp_gains x where x.character_id = g.character_id and x.source = ? and x.source_id = g.quest)
                ) as turned_in
            ) held
            where g.counted > greatest(g.claims - held.turned_in, 0)
            order by ch.name, g.quest
            limit 20
            SQL,
            [ClaimStatus::Counted->value, AddonKind::Xp->value, $characterId, $characterId, QuestRecord::TURNED_IN, XpSource::Quest->value],
        ) as $row) {
            $lines[] = "{$row->character_name} counts {$row->counted} of {$row->claims} claims of quest {$row->quest} and Scarlet holds {$row->turned_in} turn-ins of it";
        }

        return $lines;
    }

    /** @return list<string> */
    private function questRowsShared(?int $characterId): array
    {
        $lines = [];
        foreach ($this->rows(
            <<<'SQL'
            select ch.name as character_name, c.evidence #>> '{rows,first}' as at_ms, count(*) as claims
            from addon_claims c join characters ch on ch.id = c.character_id
            where c.kind = ? and c.status = ? and c.evidence ->> 'rule' = '2' and (c.evidence #>> '{rows,n}')::int = 1
                and (?::bigint is null or c.character_id = ?)
            group by c.character_id, ch.name, c.evidence #>> '{rows,first}', c.evidence #>> '{rows,quest}'
            having count(*) > 1
            order by ch.name
            limit 20
            SQL,
            [AddonKind::Xp->value, ClaimStatus::Superseded->value, $characterId, $characterId],
        ) as $row) {
            $lines[] = "{$row->character_name}: the Scarlet turn-in at {$row->at_ms} is the evidence of {$row->claims} quest claims";
        }

        return $lines;
    }

    /** @return string the condition on `r.character_id` that reads the runs of every character that has a run claim */
    private function runCharacters(): string
    {
        return 'r.character_id in (select a.character_id from addon_claims a where a.kind = ?)';
    }

    /** @return list<string> */
    private function runsBesideRows(?int $characterId): array
    {
        $lines = [];
        foreach ($this->rows(
            'with '.JoinedDungeonRuns::cte($this->runCharacters()).<<<'SQL'

            select c.id, ch.name as character_name, c.origin, c.to_at
            from addon_claims c join characters ch on ch.id = c.character_id
            where c.kind = ? and c.status = ? and (?::bigint is null or c.character_id = ?)
                and exists (
                    select 1 from joined j
                    where j.character_id = c.character_id and (
                        (j.instance_id = (c.dims ->> 'instance')::int and j.at <= to_timestamp(c.to_at + ?) and j.end_at >= to_timestamp(coalesce(c.from_at, c.to_at) - ?))
                        or (j.at <= to_timestamp(c.to_at) and j.end_at >= to_timestamp(coalesce(c.from_at, c.to_at)))
                    )
                )
            order by c.id
            limit 20
            SQL,
            [AddonKind::Run->value, AddonKind::Run->value, ClaimStatus::Counted->value, $characterId, $characterId, self::RUN_AFTER, self::RUN_BEFORE],
        ) as $row) {
            $lines[] = "claim {$row->id} of {$row->character_name} ({$row->origin} run to {$row->to_at}) counts and a joined Scarlet run lies beside it";
        }

        return $lines;
    }

    /** @return list<string> */
    private function runsApart(?int $characterId): array
    {
        $lines = [];
        foreach ($this->rows(
            <<<'SQL'
            select * from (
                select c.id, ch.name as character_name, c.origin, c.to_at, coalesce(c.from_at, c.to_at) as start_at,
                    lag(c.to_at) over (partition by c.character_id, c.dims ->> 'instance' order by coalesce(c.from_at, c.to_at), c.to_at) as before_to
                from addon_claims c join characters ch on ch.id = c.character_id
                where c.kind = ? and c.status = ? and (?::bigint is null or c.character_id = ?)
            ) t
            where start_at - before_to < ?
            order by id
            limit 20
            SQL,
            [AddonKind::Run->value, ClaimStatus::Counted->value, $characterId, $characterId, self::RUN_JOIN],
        ) as $row) {
            $lines[] = "claim {$row->id} of {$row->character_name} ({$row->origin} run to {$row->to_at}) counts less than ".self::RUN_JOIN.' seconds after another run of its instance that counts';
        }

        return $lines;
    }

    /**
     * What a character holds of Scarlet's rows beside a claim, the way the rule of its kind assigns them, as a scalar
     * subquery over the claim `$claim` and the values its `?` take.
     *
     * @return array{0: string, 1: list<int|string>}
     */
    private function beside(AddonKind $kind, string $claim): array
    {
        if ($kind === AddonKind::Kill) {
            return ["(select count(*) from kills k where k.character_id = {$claim}.character_id and not k.player and (k.level = {$claim}.level or k.level is null))", []];
        }
        if ($kind === AddonKind::Xp) {
            return [
                "greatest((select count(*) from quest_events q where q.character_id = {$claim}.character_id and q.event = ? and q.quest_id = ({$claim}.dims ->> 'quest')::int), (select count(*) from xp_gains x where x.character_id = {$claim}.character_id and x.source = ? and x.source_id = ({$claim}.dims ->> 'quest')::int))",
                [QuestRecord::TURNED_IN, XpSource::Quest->value],
            ];
        }

        return [
            "(select count(*) from joined j where j.character_id = {$claim}.character_id and ((j.instance_id = ({$claim}.dims ->> 'instance')::int and j.at <= to_timestamp({$claim}.to_at + ?) and j.end_at >= to_timestamp(coalesce({$claim}.from_at, {$claim}.to_at) - ?)) or (j.at <= to_timestamp({$claim}.to_at) and j.end_at >= to_timestamp(coalesce({$claim}.from_at, {$claim}.to_at)))))",
            [self::RUN_AFTER, self::RUN_BEFORE],
        ];
    }

    /** @return list<string> */
    private function twinsOnFewerRows(AddonKind $kind, ?int $characterId): array
    {
        [$loser, $loserBindings] = $this->beside($kind, 's');
        [$winner, $winnerBindings] = $this->beside($kind, 'w');
        $with = $kind === AddonKind::Run ? 'with '.JoinedDungeonRuns::cte($this->runCharacters()).' ' : '';
        $lines = [];
        foreach ($this->rows(
            $with.<<<SQL
            select * from (
                select s.id as loser_id, w.id as winner_id, cs.name as loser_name, cw.name as winner_name, s.origin, s.kind, s.to_at,
                    {$loser} as loser_rows, {$winner} as winner_rows
                from addon_claims s
                join characters cs on cs.id = s.character_id
                join characters cw on cw.name = s.evidence #>> '{with,character}'
                join addon_claims w on w.character_id = cw.id and w.origin = s.origin and w.kind = s.kind and w.window = s.window and w.scope = s.scope
                    and w.from_at is not distinct from s.from_at and w.to_at = s.to_at and w.amounts = s.amounts and w.dims = s.dims
                where s.status = ? and s.evidence ->> 'rule' = '4' and s.kind = ?
                    and (?::bigint is null or s.character_id = ? or cw.id = ?)
            ) t
            where loser_rows > winner_rows
            order by loser_id
            limit 20
            SQL,
            [...($kind === AddonKind::Run ? [AddonKind::Run->value] : []), ...$loserBindings, ...$winnerBindings, ClaimStatus::Superseded->value, $kind->value, $characterId, $characterId, $characterId],
        ) as $row) {
            $lines[] = "claim {$row->loser_id} of {$row->loser_name} is superseded by claim {$row->winner_id} of {$row->winner_name}, which holds {$row->winner_rows} Scarlet rows beside the record ({$row->origin} {$row->kind} to {$row->to_at}) while {$row->loser_name} holds {$row->loser_rows}";
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
