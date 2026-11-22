<?php

namespace App\Actions\Public;

use App\Actions\Rollups\CraftDifficulty;
use App\Actions\Rollups\CraftHours;
use App\Actions\Rollups\CraftPerPoint;
use App\Actions\Rollups\CraftReagents;
use App\Actions\Rollups\CraftRecipeCount;
use App\Actions\Rollups\CraftRecipes;
use App\Actions\Rollups\CraftSittings;
use App\Actions\Rollups\CraftTotals;
use App\Actions\Rollups\CraftZones;
use App\Actions\Rollups\RankByLevel;
use App\Actions\Rollups\RankSummary;
use App\Actions\Rollups\ScopeCharacters;
use App\Support\Board;
use App\Support\NameKind;
use App\Support\Profession;
use App\Support\Rollup;
use App\Support\Server;

class CraftingDashboard
{
    /** The rows of the board of crafts. */
    private const int BOARD_ROWS = 10;

    public function __construct(
        private readonly ResolveScope $resolveScope,
        private readonly ListRulesets $rulesets,
        private readonly Leaderboard $leaderboard,
        private readonly RecipeFacts $recipeFacts,
        private readonly ResolveNames $names,
        private readonly ScopeCharacters $scopeCharacters,
        private readonly CraftTotals $totals,
        private readonly CraftSittings $sittings,
        private readonly CraftRecipeCount $recipeCount,
        private readonly CraftRecipes $recipes,
        private readonly CraftDifficulty $difficulty,
        private readonly CraftPerPoint $perPoint,
        private readonly CraftHours $hours,
        private readonly CraftReagents $reagents,
        private readonly CraftZones $zones,
        private readonly RankByLevel $byLevel,
        private readonly RankSummary $rank,
    ) {}

    /**
     * Everything the page of one crafting profession and `/public/professions/{slug}` show for the rulesets of the scope,
     * from the crafting aggregates. A number or a row that fewer than `config('scarlet.min_characters')` characters
     * contributed to is null or missing: the totals are zero and `characters` is null while the whole scope is under the
     * threshold, and so they are for a profession nobody has crafted with.
     *
     * @return array<string, mixed>|null null when the ruleset is not known
     */
    public function handle(Profession $profession, ?string $ruleset = null, Server $server = Server::All): ?array
    {
        $scope = $this->resolveScope->handle($ruleset, $server);
        if ($scope === null) {
            return null;
        }
        $ids = $scope->ids;

        return [
            'scope' => $scope,
            'rulesets' => $this->rulesets->handle(),
            'profession' => $profession->slug(),
            'totals' => $this->totalsOf($profession, $ids),
            'recipes' => $this->recipesOf($profession, $ids),
            'difficulty' => $this->difficulty->handle($profession, $ids),
            'per_point' => $this->perPoint->handle($profession, $ids),
            'by_level' => $this->byLevel->handle(Rollup::CraftsDailyCharacter, 'profession_id', (int) $profession->skillLine(), $ids),
            'hours' => $this->hours->handle($profession, $ids),
            'reagents' => $this->reagents->handle($profession, $ids),
            'zones' => $this->zones->handle($profession, $ids),
            'board' => $this->boardOf($profession, $ruleset, $server),
        ];
    }

    /**
     * @param  int|list<int>|null  $ids
     * @return array<string, mixed>
     */
    private function totalsOf(Profession $profession, int|array|null $ids): array
    {
        $scopeCharacters = $this->scopeCharacters->handle($ids);
        $totals = $this->totals->handle($profession, $ids);
        if ($totals === null) {
            return [
                'crafts' => 0, 'made' => 0, 'characters' => null, 'scope_characters' => $scopeCharacters, 'recipes' => 0, 'sittings' => 0,
                'median_sitting_crafts' => null, 'median_sitting_seconds' => null, 'median_gap' => null, 'skill_ups' => 0, 'trivial' => 0,
                'median_skill' => null, 'at_cap' => null,
            ];
        }
        $sittings = $this->sittings->handle($profession, $ids);
        $rank = $this->rank->handle(Rollup::CraftsDailyCharacter, 'profession_id', (int) $profession->skillLine(), $ids);

        return [
            'crafts' => $totals['crafts'],
            'made' => $totals['made'],
            'characters' => $totals['characters'],
            'scope_characters' => $scopeCharacters,
            'recipes' => $this->recipeCount->handle($profession, $ids),
            'sittings' => $totals['sittings'],
            'median_sitting_crafts' => $sittings['median_crafts'],
            'median_sitting_seconds' => $sittings['median_seconds'],
            'median_gap' => $totals['median_gap'],
            'skill_ups' => $totals['skill_ups'],
            'trivial' => $totals['trivial'],
            'median_skill' => $rank['median_skill'],
            'at_cap' => $rank['at_cap'],
        ];
    }

    /**
     * @param  int|list<int>|null  $ids
     * @return list<array<string, mixed>>
     */
    private function recipesOf(Profession $profession, int|array|null $ids): array
    {
        $rows = $this->recipes->handle($profession, $ids);
        $facts = $this->recipeFacts->handle(array_map(fn (array $row): int => $row['recipe_id'], $rows));
        $itemIds = [];
        foreach ($rows as $row) {
            $itemIds[] = $row['item_id'] ?? $facts[$row['recipe_id']]['output']['item_id'] ?? null;
            foreach ($facts[$row['recipe_id']]['reagents'] ?? [] as $reagent) {
                $itemIds[] = $reagent['item_id'];
            }
        }
        $names = $this->names->handle(NameKind::Item, $itemIds);

        return array_map(function (array $row) use ($facts, $names): array {
            $itemId = $row['item_id'] ?? $facts[$row['recipe_id']]['output']['item_id'] ?? null;

            return [
                ...$row,
                'item_id' => $itemId,
                'item' => $itemId === null ? null : ($names[$itemId] ?? null),
                'reagents' => array_map(
                    fn (array $reagent): array => ['item_id' => $reagent['item_id'], 'name' => $names[$reagent['item_id']] ?? null, 'quantity' => $reagent['quantity']],
                    $facts[$row['recipe_id']]['reagents'] ?? [],
                ),
            ];
        }, $rows);
    }

    /** @return list<array<string, mixed>> */
    private function boardOf(Profession $profession, ?string $ruleset, Server $server): array
    {
        $board = Board::ofCrafting($profession);
        if ($board === null) {
            return [];
        }
        $answer = $this->leaderboard->handle($board, $ruleset, $server, null, 0, self::BOARD_ROWS);

        return array_slice($answer['rows'] ?? [], 0, self::BOARD_ROWS);
    }
}
