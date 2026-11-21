<?php

namespace App\Actions\Public;

use App\Actions\Rollups\BusiestZones;
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
use App\Actions\Rollups\XpSourcesByLevel;
use App\Support\Series;
use App\Support\Server;

class RulesetSeries
{
    public function __construct(
        private readonly ResolveScope $resolveScope,
        private readonly LevellingCurve $curve,
        private readonly TimePerLevel $timePerLevel,
        private readonly XpSourcesByLevel $xp,
        private readonly DeathsByLevel $deathsByLevel,
        private readonly TopKillers $killers,
        private readonly DeathZones $deathZones,
        private readonly DungeonRuns $dungeons,
        private readonly Professions $professions,
        private readonly GoldByLevel $gold,
        private readonly PlayTimeByWeekday $weekdays,
        private readonly PlayTimeByLevel $playByLevel,
        private readonly BusiestZones $zones,
    ) {}

    /**
     * One named series of a ruleset (or of all rulesets when the slug is null). Null when the ruleset is not known.
     * Only the reads of that series run.
     *
     * @return array<string, mixed>|null
     */
    public function handle(?string $ruleset, Series $series): ?array
    {
        $scope = $this->resolveScope->handle($ruleset, Server::All);
        if ($scope === null) {
            return null;
        }
        $ids = $scope->ids;

        return match ($series) {
            Series::Levels => ['levels' => $this->curve->handle($ids), 'time_per_level' => $this->timePerLevel->handle($ids)],
            Series::Xp => ['xp_by_level' => $this->xp->handle($ids)],
            Series::Deaths => ['by_level' => $this->deathsByLevel->handle($ids), 'killers' => $this->killers->handle($ids, 20), 'zones' => $this->deathZones->handle($ids, limit: 20)],
            Series::Dungeons => ['dungeons' => $this->dungeons->handle($ids)],
            Series::Professions => ['skills' => $this->professions->handle($ids)],
            Series::Gold => ['by_level' => $this->gold->handle($ids)],
            Series::Activity => ['weekdays' => $this->weekdays->handle($ids), 'by_level' => $this->playByLevel->handle($ids), 'zones' => $this->zones->handle($ids, 20)],
        };
    }
}
