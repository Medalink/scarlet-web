<?php

namespace App\Actions\Ingest;

use App\Actions\Public\QuestRecord;
use App\Support\AddonKind;
use App\Support\ClaimStatus;
use App\Support\GatherKind;
use App\Support\JoinedDungeonRuns;
use App\Support\XpSource;
use Illuminate\Support\Facades\DB;

class ExplainAddonClaims
{
    /**
     * The table that explains each character's total of a kind (docs/DESIGN.md, Addon counts, Checks): one line per
     * status with its claims, what they claimed, what counts and the rule most of them name, and for a kind Scarlet
     * has rows of, Scarlet's own term and the total of Scarlet and the claims that add (counted and residual). It
     * reads what reconcile stored. With no character, every character is explained.
     *
     * @return list<array{character: string, kind: string, statuses: list<array{status: string, claims: int, claimed: array<string, int|float>, counted: array<string, int|float>, rule: string}>, scarlet: array<string, int>|null, total: array<string, int|float>|null}>
     */
    public function handle(?int $characterId): array
    {
        /** @var array<string, array{id: int, character: string, kind: string}> $heads */
        $heads = [];
        /** @var array<string, array<string, array{status: string, claims: int, claimed: array<string, int|float>, counted: array<string, int|float>, rule: string}>> $statuses */
        $statuses = [];
        foreach (DB::select(
            <<<'SQL'
            select c.character_id, ch.name as character_name, c.kind, coalesce(c.status, 'none') as status, count(*) as claims,
                coalesce(mode() within group (order by c.evidence ->> 'rule'), '') as rule
            from addon_claims c join characters ch on ch.id = c.character_id
            where ?::bigint is null or c.character_id = ?
            group by c.character_id, ch.name, c.kind, c.status
            order by ch.name, c.kind
            SQL,
            [$characterId, $characterId],
        ) as $row) {
            $key = $row->character_id.'|'.$row->kind;
            $heads[$key] = ['id' => (int) $row->character_id, 'character' => (string) $row->character_name, 'kind' => (string) $row->kind];
            $statuses[$key][(string) $row->status] = ['status' => (string) $row->status, 'claims' => (int) $row->claims, 'claimed' => [], 'counted' => [], 'rule' => (string) $row->rule];
        }
        foreach (DB::select(
            <<<'SQL'
            select c.character_id, c.kind, coalesce(c.status, 'none') as status, a.metric,
                sum((a.value #>> '{}')::numeric) as claimed, sum(coalesce((c.counted ->> a.metric)::numeric, 0)) as counted
            from addon_claims c cross join lateral jsonb_each(c.amounts) as a(metric, value)
            where ?::bigint is null or c.character_id = ?
            group by c.character_id, c.kind, c.status, a.metric
            SQL,
            [$characterId, $characterId],
        ) as $row) {
            $key = $row->character_id.'|'.$row->kind;
            $entry = $statuses[$key][(string) $row->status] ?? null;
            if ($entry === null) {
                continue;
            }
            $entry['claimed'][(string) $row->metric] = $this->number((string) $row->claimed);
            $entry['counted'][(string) $row->metric] = $this->number((string) $row->counted);
            $statuses[$key][(string) $row->status] = $entry;
        }

        $explained = [];
        foreach ($heads as $key => $head) {
            $lines = array_values($statuses[$key]);
            usort($lines, fn (array $a, array $b): int => $this->order($a['status']) <=> $this->order($b['status']));
            $kind = AddonKind::from($head['kind']);
            $scarlet = $kind->hasRows() ? $this->scarlet($head['id'], $kind) : null;
            $explained[] = [
                'character' => $head['character'],
                'kind' => $head['kind'],
                'statuses' => $lines,
                'scarlet' => $scarlet,
                'total' => $scarlet === null ? null : $this->total($scarlet, $lines),
            ];
        }

        return $explained;
    }

    private function order(string $status): int
    {
        $at = array_search($status, array_map(fn (ClaimStatus $case): string => $case->value, ClaimStatus::cases()), true);

        return $at === false ? count(ClaimStatus::cases()) : (int) $at;
    }

    private function number(string $value): int|float
    {
        return str_contains($value, '.') ? (float) $value : (int) $value;
    }

    /**
     * Scarlet's own term of the kind: the figure the counted claims add to. Fishing: attempts, looted attempts and
     * the quantity of the item rows. Kill: the kills that are not players. Xp: the experience of the quests turned
     * in, the larger of what `quest_events` and `xp_gains` of source quest hold, since the two sharing groups are
     * on or off apart. Run: the dungeon runs, joined.
     *
     * @return array<string, int>
     */
    private function scarlet(int $characterId, AddonKind $kind): array
    {
        if ($kind === AddonKind::Kill) {
            return ['kills' => DB::table('kills')->where('character_id', $characterId)->where('player', false)->count()];
        }
        if ($kind === AddonKind::Xp) {
            $events = (int) DB::table('quest_events')->where('character_id', $characterId)->where('event', QuestRecord::TURNED_IN)->sum('xp');
            $gains = (int) DB::table('xp_gains')->where('character_id', $characterId)->where('source', XpSource::Quest->value)->sum('amount');

            return ['xp' => max($events, $gains)];
        }
        if ($kind === AddonKind::Run) {
            $joined = DB::selectOne('with '.JoinedDungeonRuns::cte('r.character_id = ?').' select count(*) as runs from joined', [$characterId]);

            return ['runs' => (int) ($joined->runs ?? 0)];
        }
        $fish = GatherKind::Fish->value;
        $gathers = DB::table('gathers')->where('character_id', $characterId)->where('kind', $fish)
            ->selectRaw('count(*) as casts, count(*) filter (where looted) as landed')->first();
        $items = DB::table('gather_items')->where('character_id', $characterId)->where('kind', $fish)->whereNull('container_item_id')->sum('quantity');

        return ['casts' => (int) ($gathers->casts ?? 0), 'landed' => (int) ($gathers->landed ?? 0), 'items' => (int) $items];
    }

    /**
     * @param  array<string, int>  $scarlet
     * @param  list<array{status: string, claims: int, claimed: array<string, int|float>, counted: array<string, int|float>, rule: string}>  $statuses
     * @return array<string, int|float>
     */
    private function total(array $scarlet, array $statuses): array
    {
        $total = $scarlet;
        foreach ($statuses as $status) {
            if (in_array($status['status'], ClaimStatus::adding(), true)) {
                foreach (array_keys($scarlet) as $metric) {
                    $total[$metric] += $status['counted'][$metric] ?? 0;
                }
            }
        }

        return $total;
    }
}
