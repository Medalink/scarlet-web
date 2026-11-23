<?php

namespace App\Actions\Public;

use App\Actions\Feats\ShowFeatCatalog;
use App\Actions\Rollups\DeathsByLevel;
use App\Actions\Rollups\LevellingCurve;
use App\Actions\Rollups\PlayTimeByLevel;
use App\Actions\Rollups\PlayTimeByWeekday;
use App\Actions\Rollups\ReachedLevels;
use App\Actions\Rollups\Roster;
use App\Actions\Rollups\Totals;
use App\Support\Board;
use App\Support\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** What the front page draws: the site's totals, the levelling curve, deaths by level, the class split, when everyone plays and who leads two boards. */
class ShowHub
{
    private const int PODIUM = 3;

    public function __construct(
        private readonly ResolveScope $scope,
        private readonly Totals $totals,
        private readonly LevellingCurve $curve,
        private readonly ReachedLevels $reached,
        private readonly DeathsByLevel $deaths,
        private readonly PlayTimeByLevel $played,
        private readonly PlayTimeByWeekday $weekdays,
        private readonly Roster $roster,
        private readonly Leaderboard $boards,
        private readonly ListRulesets $rulesets,
        private readonly ShowFeatCatalog $catalog,
    ) {}

    /**
     * Kept for the time the edge keeps a page, so a signed-in visitor, who bypasses the edge, costs no more queries than a signed-out one.
     *
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        return Cache::remember(self::key(), (int) config('scarlet.edge.page_seconds'), fn (): array => $this->read());
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        $scope = $this->scope->handle(null, Server::All);
        $ids = $scope === null ? [] : $scope->ids;
        $feats = DB::table('feat_events')->whereColumn('tier', '>', 'from_tier')
            ->selectRaw("coalesce(sum(tier - from_tier), 0) as total, coalesce(sum(tier - from_tier) filter (where at >= now() - interval '7 days'), 0) as week")->first();

        return [
            'totals' => $this->totals->handle($ids),
            'curve' => $this->curve->handle($ids),
            'reached' => $this->reached->handle($ids),
            'deaths' => $this->deaths->handle($ids),
            'played' => $this->played->handle($ids),
            'weekdays' => $this->weekdays->handle($ids),
            'roster' => $this->roster->handle($ids),
            'fastest' => $this->podium(Board::FastestTo60),
            'clean' => $this->podium(Board::HighestWithoutDying),
            'boards' => count(Board::cases()),
            'rulesets' => count($this->rulesets->handle()),
            'feats' => ['kinds' => count($this->catalog->handle()['feats']), 'tiers' => (int) ($feats->total ?? 0), 'week' => (int) ($feats->week ?? 0)],
        ];
    }

    private static function key(): string
    {
        return 'hub-answer';
    }

    /** @return list<array<string, mixed>> */
    private function podium(Board $board): array
    {
        $answer = $this->boards->handle($board, null, Server::All, null, 0, self::PODIUM);

        return $answer === null ? [] : array_map(fn (array $row): array => ['name' => (string) $row['name'], 'class' => (string) $row['class'], 'value' => $board->format($row['value'])], array_slice($answer['rows'], 0, self::PODIUM));
    }
}
