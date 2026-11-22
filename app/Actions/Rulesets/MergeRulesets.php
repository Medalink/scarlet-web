<?php

namespace App\Actions\Rulesets;

use App\Actions\Ingest\RebuildCharacterLevels;
use App\Actions\Rollups\RefreshAggregates;
use App\Models\Ruleset;
use App\Models\RulesetKind;
use App\Support\DecompressionLimit;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MergeRulesets
{
    /** Far enough past the newest row for the refresh to cover every bucket. */
    private const int REFRESH_UNTIL = 4102444800;

    public function __construct(
        private readonly ResolveCharacterKind $kinds,
        private readonly RebuildCharacterLevels $levels,
        private readonly RefreshAggregates $refresh,
    ) {}

    /**
     * Move every session, and every fact row of it, from the ruleset rows of the signals era to the row of its
     * character's kind (docs/DESIGN.md, Rulesets), or move them back. A character's kind is the session's hardcore flag,
     * else what `ResolveCharacterKind` says, else the kind the old rows were marked with. Each old row names the row its
     * sessions went to (`merged_into`) and each session keeps its old row (`original_ruleset_id`). One transaction per
     * character, under the lock ingest takes, so a session is never filed under two rows. Safe to run twice.
     *
     * Undoing is only sound together with the previous code: a session decoded again is filed under its kind again.
     *
     * @return array{tables: list<array{table: string, before: int, after: int}>, rulesets: list<array{key: string, before: int, after: int}>, checks: list<array{name: string, ok: bool, detail: string}>, notes: list<string>}
     */
    public function handle(bool $undo = false): array
    {
        $tables = $this->factTables();
        $this->refresh->handle(0, self::REFRESH_UNTIL);
        $before = $this->snapshot($tables);

        $notes = $undo ? $this->undo($tables) : $this->merge($tables);
        $this->refresh->handle(0, self::REFRESH_UNTIL);

        $after = $this->snapshot($tables);

        return [
            'tables' => array_map(fn (string $table): array => ['table' => $table, 'before' => $before['counts'][$table], 'after' => $after['counts'][$table]], array_keys($before['counts'])),
            'rulesets' => $this->rulesetCounts($before['rulesets'], $after['rulesets']),
            'checks' => $this->checks($before, $after, $tables),
            'notes' => $notes,
        ];
    }

    /**
     * @param  array<string, bool>  $tables
     * @return list<string>
     */
    private function merge(array $tables): array
    {
        $notes = [];
        $rows = $this->kindRows();
        $unfiled = 0;
        foreach (DB::table('sessions')->distinct()->orderBy('character_id')->pluck('character_id') as $characterId) {
            $notes = [...$notes, ...DB::transaction(fn (): array => $this->file((int) $characterId, $rows, $tables))];
        }
        $this->mergedInto($rows);
        foreach ($tables as $table => $linked) {
            $unfiled += DB::transaction(function () use ($table): int {
                DecompressionLimit::lift();

                return DB::table($table.' as f')
                    ->whereExists(fn ($old) => $old->selectRaw('1')->from('rulesets as r')->whereColumn('r.id', 'f.ruleset_id')->whereNotNull('r.merged_into'))
                    ->update(['ruleset_id' => DB::raw('(select r2.merged_into from rulesets r2 where r2.id = f.ruleset_id)')]);
            });
        }
        $notes[] = $unfiled.' fact rows without a session were filed through their old ruleset row.';

        $characters = DB::table('sessions')->where('xp_off', true)->distinct()->pluck('character_id');
        foreach ($characters as $characterId) {
            DB::transaction(fn () => $this->levels->handle((int) $characterId));
        }
        $notes[] = $characters->count().' characters with experience off had their level rows rebuilt.';

        return $notes;
    }

    /**
     * @param  array<string, bool>  $tables
     * @return list<string>
     */
    private function undo(array $tables): array
    {
        foreach (DB::table('sessions')->whereNotNull('original_ruleset_id')->distinct()->orderBy('character_id')->pluck('character_id') as $characterId) {
            DB::transaction(function () use ($characterId, $tables): void {
                DecompressionLimit::lift();
                $this->lock((int) $characterId);
                DB::table('sessions')->where('character_id', $characterId)->whereNotNull('original_ruleset_id')
                    ->update(['ruleset_id' => DB::raw('original_ruleset_id'), 'original_ruleset_id' => null]);
                $this->follow((int) $characterId, $tables);
            });
        }
        Ruleset::query()->whereNotNull('merged_into')->update(['merged_into' => null]);

        return ['Sessions are back on the rows they had. Decode none of them again before the previous code runs.'];
    }

    /**
     * File one character's sessions under the row of its kind and bring its fact rows after them.
     *
     * @param  array<string, int>  $rows  the id of each kind's row, by `Ruleset::keyFor()`
     * @param  array<string, bool>  $tables
     * @return list<string>
     */
    private function file(int $characterId, array $rows, array $tables): array
    {
        DecompressionLimit::lift();
        $this->lock($characterId);
        $sessions = DB::table('sessions as s')
            ->join('rulesets as o', 'o.id', '=', DB::raw('coalesce(s.original_ruleset_id, s.ruleset_id)'))
            ->where('s.character_id', $characterId)
            ->get(['s.session_start', 's.ruleset_id', 'o.hardcore', 'o.kind', DB::raw("s.extra->>'server_id' as server_id")]);

        $realm = $sessions->pluck('server_id')->filter()->first();
        $stored = $sessions->pluck('kind')->filter()->unique()->values();
        $kind = $this->kinds->handle($characterId, $realm === null ? null : (int) $realm) ?? ($stored->isEmpty() ? null : RulesetKind::tryFrom((string) $stored->first()));
        $notes = $stored->count() > 1 ? ['Character '.$characterId.' had sessions under rows of '.$stored->implode(', ').'; filed under '.Ruleset::keyFor($kind).'.'] : [];

        $targets = [];
        foreach ($sessions as $session) {
            $row = $session->hardcore === true ? $rows[Ruleset::keyFor(RulesetKind::Hardcore)] : $rows[Ruleset::keyFor($kind)];
            if ((int) $session->ruleset_id !== $row) {
                $targets[$row][] = (int) $session->session_start;
            }
        }
        foreach ($targets as $row => $starts) {
            DB::table('sessions')->where('character_id', $characterId)->whereIn('session_start', $starts)
                ->update(['original_ruleset_id' => DB::raw('coalesce(original_ruleset_id, ruleset_id)'), 'ruleset_id' => $row]);
        }
        $this->follow($characterId, $tables);

        return $notes;
    }

    /**
     * Give each fact row of the character the ruleset of its session.
     *
     * @param  array<string, bool>  $tables
     */
    private function follow(int $characterId, array $tables): void
    {
        foreach ($tables as $table => $linked) {
            if (! $linked) {
                continue;
            }
            DB::table($table.' as f')
                ->where('f.character_id', $characterId)
                ->whereExists(fn ($session) => $session->selectRaw('1')->from('sessions as s')->whereColumn('s.character_id', 'f.character_id')->whereColumn('s.session_start', 'f.session_start')->whereColumn('s.ruleset_id', '<>', 'f.ruleset_id'))
                ->update(['ruleset_id' => DB::raw('(select s2.ruleset_id from sessions s2 where s2.character_id = f.character_id and s2.session_start = f.session_start)')]);
        }
    }

    private function lock(int $characterId): void
    {
        DB::table('characters')->where('id', $characterId)->lockForUpdate()->exists();
    }

    /**
     * The row of each kind and of no kind, made when missing.
     *
     * @return array<string, int>
     */
    private function kindRows(): array
    {
        $rows = [];
        foreach ([...RulesetKind::cases(), null] as $kind) {
            $key = Ruleset::keyFor($kind);
            Ruleset::query()->insertOrIgnore(['key' => $key, 'war_mode' => $kind === null ? Ruleset::WAR_MODE_UNKNOWN : $kind->warMode(), 'kind' => $kind?->value]);
            $rows[$key] = (int) Ruleset::query()->where('key', $key)->value('id');
        }

        return $rows;
    }

    /**
     * Name, on each old row, the row most of its sessions went to; a row without sessions goes where its kind goes.
     *
     * @param  array<string, int>  $rows
     */
    private function mergedInto(array $rows): void
    {
        $went = DB::table('sessions')->whereNotNull('original_ruleset_id')->groupBy('original_ruleset_id', 'ruleset_id')
            ->get(['original_ruleset_id', 'ruleset_id', DB::raw('count(*) as sessions')]);
        $best = [];
        foreach ($went as $row) {
            $old = (int) $row->original_ruleset_id;
            if (! isset($best[$old]) || $best[$old]['sessions'] < (int) $row->sessions) {
                $best[$old] = ['to' => (int) $row->ruleset_id, 'sessions' => (int) $row->sessions];
            }
        }
        foreach (Ruleset::query()->whereNotIn('key', Ruleset::kindKeys())->get() as $old) {
            $target = $best[$old->id]['to'] ?? $rows[Ruleset::keyFor($old->kind === null ? null : RulesetKind::tryFrom($old->kind))];
            Ruleset::query()->whereKey($old->id)->update(['merged_into' => $target]);
        }
    }

    /**
     * Every table of the public schema that files rows by ruleset, and whether each row names its session.
     *
     * @return array<string, bool>
     */
    private function factTables(): array
    {
        $names = DB::table('information_schema.columns as c')
            ->join('information_schema.tables as t', fn (JoinClause $join) => $join->on('t.table_schema', '=', 'c.table_schema')->on('t.table_name', '=', 'c.table_name'))
            ->where('c.table_schema', 'public')->where('t.table_type', 'BASE TABLE')->where('c.column_name', 'ruleset_id')
            ->whereNotIn('c.table_name', ['sessions', 'rulesets'])->orderBy('c.table_name')->pluck('c.table_name');

        $tables = [];
        foreach ($names as $name) {
            $tables[(string) $name] = Schema::hasColumns((string) $name, ['character_id', 'session_start']);
        }

        return $tables;
    }

    /**
     * What the merge must leave as it was, or change as expected.
     *
     * @param  array<string, bool>  $tables
     * @return array{counts: array<string, int>, rulesets: array<string, int>, hours: array{characters: int, sum: int, digest: string}, deaths: array{characters: int, sum: int, digest: string}, xp: array{characters: int, sum: int, digest: string}, rollups: array<string, int>}
     */
    private function snapshot(array $tables): array
    {
        $counts = ['sessions' => DB::table('sessions')->count()];
        foreach ($tables as $table => $linked) {
            $counts[$table] = DB::table($table)->count();
        }
        $rulesets = [];
        foreach (DB::table('sessions as s')->join('rulesets as r', 'r.id', '=', 's.ruleset_id')->groupBy('r.key')->selectRaw('r.key, count(*) as n')->get() as $row) {
            $rulesets[(string) $row->key] = (int) $row->n;
        }

        return [
            'counts' => $counts,
            'rulesets' => $rulesets,
            'hours' => $this->perCharacter(DB::table('play_spans as t')->selectRaw('sum(t.ms) as figure')),
            'deaths' => $this->perCharacter(DB::table('deaths as t')->selectRaw('count(*) as figure')),
            'xp' => $this->perCharacter(DB::table('xp_gains as t')->selectRaw('sum(t.amount) as figure')),
            'rollups' => [
                'play_hourly_ruleset.ms' => (int) DB::table('play_hourly_ruleset')->sum('ms'),
                'deaths_daily_ruleset.deaths' => (int) DB::table('deaths_daily_ruleset')->sum('deaths'),
                'xp_daily_ruleset.xp' => (int) DB::table('xp_daily_ruleset')->sum('xp'),
            ],
        ];
    }

    /**
     * One figure (the query's `figure` column, from a fact table aliased `t`) per character, over the rows that have a
     * ruleset row: the number of characters, the sum of the figures and a digest of the list.
     *
     * @return array{characters: int, sum: int, digest: string}
     */
    private function perCharacter(Builder $figure): array
    {
        $rows = $figure->addSelect('t.character_id')->join('rulesets as r', 'r.id', '=', 't.ruleset_id')->groupBy('t.character_id')->orderBy('t.character_id')->get();

        return [
            'characters' => $rows->count(),
            'sum' => (int) $rows->sum(fn (\stdClass $row): int => (int) $row->figure),
            'digest' => md5($rows->map(fn (\stdClass $row): string => $row->character_id.':'.$row->figure)->implode(',')),
        ];
    }

    /**
     * @param  array<string, int>  $before
     * @param  array<string, int>  $after
     * @return list<array{key: string, before: int, after: int}>
     */
    private function rulesetCounts(array $before, array $after): array
    {
        $keys = array_values(array_unique([...array_keys($before), ...array_keys($after)]));
        sort($keys);

        return array_map(fn (string $key): array => ['key' => $key, 'before' => $before[$key] ?? 0, 'after' => $after[$key] ?? 0], $keys);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, bool>  $tables
     * @return list<array{name: string, ok: bool, detail: string}>
     */
    private function checks(array $before, array $after, array $tables): array
    {
        $checks = [];
        $same = $before['counts'] === $after['counts'];
        $checks[] = ['name' => 'row counts of every table', 'ok' => $same, 'detail' => $same ? 'equal' : 'changed'];
        foreach (['hours', 'deaths', 'xp'] as $figure) {
            $ok = $before[$figure] === $after[$figure];
            $checks[] = ['name' => 'per-character '.$figure, 'ok' => $ok, 'detail' => $before[$figure]['characters'].' characters, sum '.$before[$figure]['sum'].' -> '.$after[$figure]['sum'].', digest '.($ok ? 'equal' : 'differs')];
        }
        foreach ($before['rollups'] as $name => $sum) {
            $checks[] = ['name' => 'rollup '.$name, 'ok' => $sum === $after['rollups'][$name], 'detail' => $sum.' -> '.$after['rollups'][$name]];
        }
        $stray = 0;
        foreach ($tables as $table => $linked) {
            $stray += $linked ? DB::table($table.' as f')->join('sessions as s', fn (JoinClause $join) => $join->on('s.character_id', '=', 'f.character_id')->on('s.session_start', '=', 'f.session_start'))->whereColumn('f.ruleset_id', '<>', 's.ruleset_id')->count() : 0;
        }
        $checks[] = ['name' => 'fact rows under another ruleset than their session', 'ok' => $stray === 0, 'detail' => (string) $stray];

        return $checks;
    }
}
