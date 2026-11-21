<?php

namespace App\Actions\Public;

use App\Support\Board;
use App\Support\PageScope;
use App\Support\Server;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Leaderboard
{
    public function __construct(private readonly ResolveScope $resolveScope, private readonly ListRulesets $rulesets) {}

    /**
     * One board for the public characters that have counted sessions, best first, a page at a time. Reads
     * `$limit + 1` rows so the caller can tell whether a next page exists. `$viewerAccountId` marks the viewer's own
     * characters (`mine`) and changes nothing else. Null when the ruleset is not known.
     *
     * @return array{scope: PageScope, board: Board, rows: list<array<string, mixed>>, total: int, classes: list<string>}|null
     */
    public function handle(Board $board, ?string $ruleset, Server $server, ?string $class, int $offset, int $limit, ?int $viewerAccountId = null): ?array
    {
        $scope = $this->resolveScope->handle($ruleset, $server);
        if ($scope === null) {
            return null;
        }
        $labels = collect($this->rulesets->handle())->keyBy('id');

        $query = $board->level() === null ? $this->totals($board, $scope) : $this->fastest($board, $scope);
        $query->when($class !== null, fn (Builder $q): Builder => $q->where('c.class', $class));
        $total = (clone $query)->reorder()->count();

        $rows = $query->offset($offset)->limit($limit + 1)->get()->values()->map(fn (object $row, int $i): array => [
            'rank' => $offset + $i + 1,
            'name' => $row->name,
            'race' => $row->race,
            'class' => $row->class,
            'value' => is_float($row->value + 0) ? round($row->value + 0, 3) : $row->value + 0,
            'ruleset' => $labels->get($row->ruleset_id)['slug'] ?? null,
            'ruleset_label' => $labels->get($row->ruleset_id)['label'] ?? null,
            'hardcore' => (bool) ($labels->get($row->ruleset_id)['hardcore'] ?? false),
            'deaths' => (int) $row->deaths,
            'since' => $row->whole_life ? null : Carbon::parse((string) $row->first_seen_at)->utc()->toIso8601ZuluString(),
            'at' => $row->at === null ? null : Carbon::parse((string) $row->at)->utc()->toIso8601ZuluString(),
            'mine' => $viewerAccountId !== null && (int) $row->account_id === $viewerAccountId,
        ])->all();

        return [
            'scope' => $scope,
            'board' => $board,
            'rows' => array_values($rows),
            'total' => $total,
            'classes' => array_values(DB::table('characters')->where('is_public', true)->whereNotNull('class')->distinct()->orderBy('class')->pluck('class')->map(fn (mixed $class): string => (string) $class)->all()),
        ];
    }

    /**
     * Time to reach a level, as the client's played total when Scarlet saw the level-up into it, for public characters.
     * A character first seen at or above the level has no row for it, so it is not listed.
     */
    private function fastest(Board $board, PageScope $scope): Builder
    {
        $level = (int) $board->level();
        $totals = $this->sessionTotals($scope);

        return DB::table('character_levels as cl')
            ->join('characters as c', 'c.id', '=', 'cl.character_id')
            ->joinSub($totals, 's', 's.character_id', '=', 'c.id')
            ->where('c.is_public', true)
            ->where('cl.counted', true)
            ->where('cl.level', $level)
            ->when(is_int($scope->ids), fn (Builder $q): Builder => $q->where('cl.ruleset_id', $scope->ids))
            ->when(is_array($scope->ids), fn (Builder $q): Builder => $q->whereIn('cl.ruleset_id', $scope->ids))
            ->select('c.name', 'c.race', 'c.class', 'c.account_id', 'c.whole_life', 'c.first_seen_at', 's.deaths', 's.ruleset_id', 'cl.before_seconds as value', 'cl.at')
            ->orderBy('cl.before_seconds')
            ->orderBy('c.id');
    }

    /** The boards that add up sessions. */
    private function totals(Board $board, PageScope $scope): Builder
    {
        $query = DB::table('characters as c')
            ->joinSub($this->sessionTotals($scope), 's', 's.character_id', '=', 'c.id')
            ->where('c.is_public', true)
            ->select('c.name', 'c.race', 'c.class', 'c.account_id', 'c.whole_life', 'c.first_seen_at', 's.deaths', 's.ruleset_id', 's.last_at as at');

        return match ($board) {
            Board::HighestWithoutDying => $query->where('c.whole_life', true)->where('s.deaths', 0)->whereNotNull('s.level')
                ->addSelect('s.level as value')->orderByDesc('s.level')->orderBy('c.played_seconds')->orderBy('c.id'),
            Board::MostQuests => $query->where('s.quests', '>', 0)->addSelect('s.quests as value')->orderByDesc('s.quests')->orderBy('c.id'),
            Board::MostDungeonRuns => $query->where('s.runs', '>', 0)->addSelect('s.runs as value')->orderByDesc('s.runs')->orderBy('c.id'),
            default => $query->where('s.distance', '>', 0)->addSelect('s.distance as value')->orderByDesc('s.distance')->orderBy('c.id'),
        };
    }

    /** Dungeon runs of the character: a run that carries on the one before it is the same run. */
    private function runs(PageScope $scope): Builder
    {
        return DB::table('dungeon_runs as d')
            ->whereColumn('d.character_id', 'sessions.character_id')
            ->where('d.counted', true)
            ->where('d.continues_previous', false)
            ->when(is_int($scope->ids), fn (Builder $q): Builder => $q->where('d.ruleset_id', $scope->ids))
            ->when(is_array($scope->ids), fn (Builder $q): Builder => $q->whereIn('d.ruleset_id', $scope->ids))
            ->selectRaw('count(*)');
    }

    /** What a character's counted sessions add up to, one row per character, for the rulesets of the scope. */
    private function sessionTotals(PageScope $scope): Builder
    {
        return DB::table('sessions')
            ->where('counted', true)
            ->when(is_int($scope->ids), fn (Builder $q): Builder => $q->where('ruleset_id', $scope->ids))
            ->when(is_array($scope->ids), fn (Builder $q): Builder => $q->whereIn('ruleset_id', $scope->ids))
            ->groupBy('character_id')
            ->selectRaw('character_id, coalesce(sum(deaths), 0) as deaths, coalesce(sum(quests_done), 0) as quests')
            ->selectSub($this->runs($scope), 'runs')
            ->selectRaw('coalesce(sum(distance), 0) as distance, max(level_end) as level, max(end_at) as last_at')
            ->selectRaw('(array_agg(ruleset_id order by end_at desc))[1] as ruleset_id');
    }
}
