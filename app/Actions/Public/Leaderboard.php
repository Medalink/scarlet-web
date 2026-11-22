<?php

namespace App\Actions\Public;

use App\Support\Board;
use App\Support\BoardQuery;
use App\Support\PageScope;
use App\Support\Server;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Leaderboard
{
    public function __construct(private readonly ResolveScope $resolveScope, private readonly ListRulesets $rulesets, private readonly BoardQuery $boards) {}

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

        $query = $this->boards->ranked($board, $scope);
        $query->when($class !== null, fn (Builder $q): Builder => $q->where('c.class', $class));
        $total = (clone $query)->reorder()->count();

        $rows = $query->offset($offset)->limit($limit + 1)->get()->values()->map(fn (object $row, int $i): array => [
            'rank' => $offset + $i + 1,
            'name' => $row->name,
            'race' => $row->race,
            'class' => $row->class,
            'value' => $this->boards->value($row->value),
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
}
