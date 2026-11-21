<?php

namespace App\Actions\Public;

use App\Actions\Rollups\BusiestZones;
use App\Actions\Rollups\Coverage;
use App\Actions\Rollups\DeathsByLevel;
use App\Actions\Rollups\DeathZones;
use App\Actions\Rollups\DungeonRuns;
use App\Actions\Rollups\GoldByLevel;
use App\Actions\Rollups\LevellingCurve;
use App\Actions\Rollups\PlayTimeByLevel;
use App\Actions\Rollups\PlayTimeByWeekday;
use App\Actions\Rollups\Professions;
use App\Actions\Rollups\TimePerLevel;
use App\Actions\Rollups\TopKillers;
use App\Actions\Rollups\Totals;
use App\Actions\Rollups\WorldHeat;
use App\Actions\Rollups\XpSourcesByLevel;
use App\Support\Server;
use App\Support\WorldLayer;

class StatsDashboard
{
    public function __construct(
        private readonly Totals $totals,
        private readonly LevellingCurve $curve,
        private readonly TimePerLevel $timePerLevel,
        private readonly XpSourcesByLevel $xp,
        private readonly DeathsByLevel $deathsByLevel,
        private readonly PlayTimeByLevel $playByLevel,
        private readonly TopKillers $killers,
        private readonly DeathZones $deathZones,
        private readonly DungeonRuns $dungeons,
        private readonly Professions $professions,
        private readonly GoldByLevel $gold,
        private readonly PlayTimeByWeekday $weekdays,
        private readonly BusiestZones $zones,
        private readonly Coverage $coverage,
        private readonly ResolveScope $resolveScope,
        private readonly ListRulesets $rulesets,
        private readonly WorldHeat $heat,
        private readonly MapZones $mapZones,
    ) {}

    /**
     * Everything the stats dashboard and `/public/stats` show for the rulesets of the scope. A number under the
     * character threshold is null or missing. Each part is one read of the aggregates.
     *
     * @return array<string, mixed>|null null when the ruleset is not known
     */
    public function handle(?string $ruleset = null, Server $server = Server::All): ?array
    {
        $scope = $this->resolveScope->handle($ruleset, $server);
        if ($scope === null) {
            return null;
        }
        $ids = $scope->ids;

        return [
            'scope' => $scope,
            'rulesets' => $this->rulesets->handle(),
            'totals' => $this->totals->handle($ids),
            'levels' => $this->curve->handle($ids),
            'level_time' => $this->timePerLevel->handle($ids),
            'xp_sources' => $this->xp->handle($ids),
            'deaths_by_level' => $this->deathsByLevel->handle($ids),
            'play_by_level' => $this->playByLevel->handle($ids),
            'killers' => $this->killers->handle($ids, 8),
            'death_zones' => $this->deathZones->handle($ids, limit: 8),
            'dungeons' => $this->dungeons->handle($ids),
            'professions' => $this->professions->handle($ids),
            'gold' => $this->gold->handle($ids),
            'weekdays' => $this->weekdays->handle($ids),
            'zones' => $this->zones->handle($ids, 12),
            'coverage' => $this->coverage->handle(),
            'heat' => $this->heat->handle(WorldLayer::Paths, $ids),
            'map_zones' => $this->mapZones->handle($ids),
        ];
    }
}
