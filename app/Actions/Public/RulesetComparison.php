<?php

namespace App\Actions\Public;

use App\Actions\Rollups\ReachedLevels;
use App\Actions\Rollups\RulesetSummaries;

class RulesetComparison
{
    public function __construct(private readonly RulesetSummaries $summaries, private readonly ListRulesets $rulesets, private readonly ReachedLevels $reached) {}

    /**
     * Every ruleset with the numbers of the comparison table and its levelling curve, and how many hardcore
     * characters have reached each level.
     *
     * @return array{rulesets: list<array{id: int, slug: string, label: string, hardcore: bool, war_mode: string}>, rows: list<array<string, mixed>>, curves: array<int, list<array{level: int, seconds: float, characters: int}>>, hardcore: list<array{level: int, characters: int}>}
     */
    public function handle(): array
    {
        $summary = $this->summaries->handle();
        $rulesets = $this->rulesets->handle();
        $hardcore = array_values(array_map(fn (array $ruleset): int => $ruleset['id'], array_filter($rulesets, fn (array $ruleset): bool => $ruleset['hardcore'])));

        return ['rulesets' => $rulesets, ...$summary, 'hardcore' => $this->reached->handle($hardcore)];
    }
}
