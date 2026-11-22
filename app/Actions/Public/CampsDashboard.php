<?php

namespace App\Actions\Public;

use App\Actions\Rollups\CampBenefitsBySpell;
use App\Actions\Rollups\CampBuffed;
use App\Actions\Rollups\CampByLevel;
use App\Actions\Rollups\CampFires;
use App\Actions\Rollups\CampHeat;
use App\Actions\Rollups\CampHours;
use App\Actions\Rollups\CampLengths;
use App\Actions\Rollups\CampTotals;
use App\Actions\Rollups\CampZones;
use App\Actions\Rollups\PlayTimeByLevel;
use App\Support\Board;
use App\Support\CampSpell;
use App\Support\Rollup;
use App\Support\RollupRead;
use App\Support\Server;

class CampsDashboard
{
    /** The rows of the board of time by the fire. */
    private const int BOARD_ROWS = 10;

    public function __construct(
        private readonly ResolveScope $resolveScope,
        private readonly ListRulesets $rulesets,
        private readonly Leaderboard $leaderboard,
        private readonly RollupRead $read,
        private readonly CampTotals $totals,
        private readonly CampFires $fires,
        private readonly CampBenefitsBySpell $benefits,
        private readonly CampLengths $lengths,
        private readonly CampByLevel $byLevel,
        private readonly PlayTimeByLevel $played,
        private readonly CampHours $hours,
        private readonly CampBuffed $buffed,
        private readonly CampZones $zones,
        private readonly CampHeat $heat,
    ) {}

    /**
     * Everything the camps page and `/public/camps` show for the rulesets of the scope, from the camp aggregates. A
     * visit is a stay that began in the session. A number or a row that fewer than `config('scarlet.min_characters')`
     * characters contributed to is null or missing: the totals are zero and `characters` is null while the whole scope is
     * under the threshold, and a campfire or benefit under it counts zero.
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
        $totals = $this->totals->handle($ids);

        return [
            'scope' => $scope,
            'rulesets' => $this->rulesets->handle(),
            'totals' => $this->totalsOf($totals, $ids),
            'fires' => $this->firesOf($ids),
            'camp_benefits' => $this->campBenefits($ids),
            'benefits' => $this->benefitsOf($ids),
            'lengths' => $this->lengths->handle($ids),
            'by_level' => $this->byLevelOf($ids),
            'hours' => $this->hours->handle($ids),
            'buffed' => $this->buffed->handle($ids),
            'zones' => $this->zones->handle($ids),
            'heat' => $this->heatOf($ids),
            'board' => $this->boardOf($ruleset, $server),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $totals
     * @param  int|list<int>|null  $ids
     * @return array<string, mixed>
     */
    private function totalsOf(?array $totals, int|array|null $ids): array
    {
        $played = $totals === null ? 0 : $this->read->sum($this->read->from(Rollup::PlayHourlyRuleset, $ids), 'ms') / 1000;
        $totals ??= array_fill_keys(['visits', 'seconds', 'earned', 'sat', 'tented', 'tent_rests', 'logged_out', 'died_after', 'afk_seconds', 'afk_known_seconds', 'crafted', 'crafts_known', 'placed_tent', 'placed_chair'], 0) + ['characters' => null, 'median_seconds' => null];

        return [
            'visits' => $totals['visits'],
            'characters' => $totals['characters'],
            'seconds' => $totals['seconds'],
            'median_seconds' => $totals['median_seconds'],
            'played_seconds' => (int) round($played),
            ...array_diff_key($totals, array_flip(['visits', 'characters', 'seconds', 'median_seconds'])),
        ];
    }

    /**
     * @param  int|list<int>|null  $ids
     * @return list<array{spell_id: int|null, label: string, visits: int}>
     */
    private function firesOf(int|array|null $ids): array
    {
        $visits = $this->fires->handle($ids);
        $rows = array_map(fn (CampSpell $spell): array => ['spell_id' => $spell->value, 'label' => $spell->label(), 'visits' => $visits[$spell->value] ?? 0], CampSpell::campfires());
        $rows[] = ['spell_id' => null, 'label' => CampSpell::FIRE_ALREADY_THERE, 'visits' => $visits[0] ?? 0];

        return $rows;
    }

    /**
     * @param  int|list<int>|null  $ids
     * @return array{gains: int, seconds: int, lost_to_death: int, characters: int|null}
     */
    private function campBenefits(int|array|null $ids): array
    {
        return $this->benefitRow($this->benefits->handle($ids)[CampSpell::CampBenefits->value] ?? null);
    }

    /**
     * @param  int|list<int>|null  $ids
     * @return list<array{spell_id: int, label: string, effect: string, gains: int, two_hour_gains: int, seconds: int, lost_to_death: int, characters: int|null}>
     */
    private function benefitsOf(int|array|null $ids): array
    {
        $seen = $this->benefits->handle($ids);
        $rows = array_map(fn (CampSpell $spell): array => [
            'spell_id' => $spell->value,
            'label' => $spell->label(),
            'effect' => (string) $spell->effect(),
            ...$this->benefitRow($seen[$spell->value] ?? null),
        ], CampSpell::benefits());
        usort($rows, fn (array $a, array $b): int => $b['gains'] <=> $a['gains']);

        return $rows;
    }

    /**
     * @param  array{gains: int, two_hour_gains: int, seconds: int, lost_to_death: int, characters: int}|null  $row
     * @return array{gains: int, two_hour_gains: int, seconds: int, lost_to_death: int, characters: int|null}
     */
    private function benefitRow(?array $row): array
    {
        return $row ?? ['gains' => 0, 'two_hour_gains' => 0, 'seconds' => 0, 'lost_to_death' => 0, 'characters' => null];
    }

    /**
     * @param  int|list<int>|null  $ids
     * @return list<array{level: int, visits: int, tent_rests: int, played_seconds: int}>
     */
    private function byLevelOf(int|array|null $ids): array
    {
        $played = [];
        foreach ($this->played->handle($ids) as $row) {
            $played[(int) $row['level']] = (int) round($row['seconds']);
        }

        return array_map(fn (array $row): array => [...$row, 'played_seconds' => $played[$row['level']] ?? 0], $this->byLevel->handle($ids));
    }

    /**
     * @param  int|list<int>|null  $ids
     * @return array{zone_id: int, map: int, name: string|null, cells: list<array{cell_x: int, cell_y: int, visits: int}>}|null
     */
    private function heatOf(int|array|null $ids): ?array
    {
        $heat = $this->heat->handle($ids);

        return $heat === null ? null : ['zone_id' => $heat['map'], ...$heat];
    }

    /** @return list<array<string, mixed>> */
    private function boardOf(?string $ruleset, Server $server): array
    {
        $board = $this->leaderboard->handle(Board::MostTimeByTheFire, $ruleset, $server, null, 0, self::BOARD_ROWS);

        return array_slice($board['rows'] ?? [], 0, self::BOARD_ROWS);
    }
}
