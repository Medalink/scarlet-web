<?php

namespace App\Actions\Public;

use App\Actions\Rollups\GatherByLevel;
use App\Actions\Rollups\GatherContents;
use App\Actions\Rollups\GatherCreatures;
use App\Actions\Rollups\GatherHeat;
use App\Actions\Rollups\GatherHours;
use App\Actions\Rollups\GatherItems;
use App\Actions\Rollups\GatherItemTotals;
use App\Actions\Rollups\GatherLures;
use App\Actions\Rollups\GatherPerPoint;
use App\Actions\Rollups\GatherPoles;
use App\Actions\Rollups\GatherSchools;
use App\Actions\Rollups\GatherSkill;
use App\Actions\Rollups\GatherTotals;
use App\Actions\Rollups\GatherTrips;
use App\Actions\Rollups\GatherWaits;
use App\Actions\Rollups\GatherZones;
use App\Actions\Rollups\ScopeCharacters;
use App\Support\Board;
use App\Support\GatherKind;
use App\Support\Rollup;
use App\Support\RollupRead;
use App\Support\Server;

class GatheringDashboard
{
    /** The rows of the board of looted attempts. */
    private const int BOARD_ROWS = 10;

    public function __construct(
        private readonly ResolveScope $resolveScope,
        private readonly ListRulesets $rulesets,
        private readonly Leaderboard $leaderboard,
        private readonly RollupRead $read,
        private readonly ScopeCharacters $scopeCharacters,
        private readonly GatherTotals $totals,
        private readonly GatherTrips $trips,
        private readonly GatherSkill $skill,
        private readonly GatherByLevel $byLevel,
        private readonly GatherItems $items,
        private readonly GatherContents $contents,
        private readonly GatherItemTotals $itemTotals,
        private readonly GatherWaits $waits,
        private readonly GatherPerPoint $perPoint,
        private readonly GatherHours $hours,
        private readonly GatherLures $lures,
        private readonly GatherPoles $poles,
        private readonly GatherCreatures $creatures,
        private readonly GatherSchools $schools,
        private readonly GatherZones $zones,
        private readonly GatherHeat $heat,
    ) {}

    /**
     * Everything the page of one gathering and `/public/professions/{slug}` show for the rulesets of the scope, from the
     * gathering aggregates. An attempt is looted when a loot window followed it. A number or a row that fewer than
     * `config('scarlet.min_characters')` characters contributed to is null or missing: the totals are zero and
     * `characters` is null while the whole scope is under the threshold.
     *
     * @return array<string, mixed>|null null when the ruleset is not known
     */
    public function handle(GatherKind $kind, ?string $ruleset = null, Server $server = Server::All): ?array
    {
        $scope = $this->resolveScope->handle($ruleset, $server);
        if ($scope === null) {
            return null;
        }
        $ids = $scope->ids;
        $lures = $kind === GatherKind::Fish ? $this->lures->handle($kind, $ids) : null;

        return [
            'scope' => $scope,
            'rulesets' => $this->rulesets->handle(),
            'kind' => $kind->value,
            'totals' => $this->totalsOf($kind, $ids, $lures['casts'] ?? null),
            'items' => $this->items->handle($kind, $ids),
            'contents' => $this->contents->handle($kind, $ids),
            'waits' => $this->waits->handle($kind, $ids),
            'per_point' => $this->perPoint->handle($kind, $ids),
            'by_level' => $this->byLevel->handle($kind, $ids),
            'hours' => $this->hours->handle($kind, $ids),
            'lures' => $lures['lures'] ?? [],
            'lured' => $lures['casts'] ?? null,
            'poles' => $this->poles->handle($kind, $ids),
            'creatures' => $kind === GatherKind::Skin ? $this->creatures->handle($kind, $ids) : [],
            'zones' => $this->zones->handle($kind, $ids),
            'heat' => $this->heatOf($kind, $ids),
            'board' => $this->boardOf($kind, $ruleset, $server),
        ];
    }

    /**
     * @param  int|list<int>|null  $ids
     * @return array<string, mixed>
     */
    private function totalsOf(GatherKind $kind, int|array|null $ids, ?int $lured): array
    {
        $totals = $this->totals->handle($kind, $ids);
        $scopeCharacters = $this->scopeCharacters->handle($ids);
        $played = $scopeCharacters === null ? 0 : $this->read->sum($this->read->from(Rollup::PlayHourlyRuleset, $ids), 'ms') / 1000;
        if ($totals === null) {
            return [
                'attempts' => 0, 'looted' => 0, 'characters' => null, 'scope_characters' => $scopeCharacters, 'seconds' => 0,
                'played_seconds' => (int) round($played), 'median_seconds' => null, 'trips' => 0, 'median_trip_seconds' => null,
                'median_trip_attempts' => null, 'in_combat' => 0, 'skill_ups' => 0, 'median_skill' => null, 'at_cap' => null,
                'items' => 0, 'container_catches' => 0, 'schools' => null, 'first_strikes' => null,
            ];
        }
        $trips = $this->trips->handle($kind, $ids);
        $skill = $this->skill->handle($kind, $ids);
        $items = $this->itemTotals->handle($kind, $ids);

        return [
            'attempts' => $totals['attempts'],
            'looted' => $totals['looted'],
            'characters' => $totals['characters'],
            'scope_characters' => $scopeCharacters,
            'seconds' => $totals['seconds'],
            'played_seconds' => (int) round($played),
            'median_seconds' => $totals['median_seconds'],
            'trips' => $totals['trips'],
            'median_trip_seconds' => $trips['median_seconds'],
            'median_trip_attempts' => $trips['median_attempts'],
            'in_combat' => $totals['in_combat'],
            'skill_ups' => $totals['skill_ups'],
            'median_skill' => $skill['median_skill'],
            'at_cap' => $skill['at_cap'],
            'items' => $items['items'],
            'container_catches' => $items['container_catches'],
            'schools' => $kind === GatherKind::Fish ? $this->schools->handle($kind, $ids) : null,
            'first_strikes' => $kind === GatherKind::Ore ? $totals['first_strikes'] : null,
        ];
    }

    /**
     * @param  int|list<int>|null  $ids
     * @return array{zone_id: int, map: int, name: string|null, cells: list<array{cell_x: int, cell_y: int, visits: int}>}|null
     */
    private function heatOf(GatherKind $kind, int|array|null $ids): ?array
    {
        $heat = $this->heat->handle($kind, $ids);

        return $heat === null ? null : ['zone_id' => $heat['map'], ...$heat];
    }

    /** @return list<array<string, mixed>> */
    private function boardOf(GatherKind $kind, ?string $ruleset, Server $server): array
    {
        $board = $this->leaderboard->handle(Board::ofGathering($kind), $ruleset, $server, null, 0, self::BOARD_ROWS);

        return array_slice($board['rows'] ?? [], 0, self::BOARD_ROWS);
    }
}
