<?php

namespace App\Actions\Public;

use App\Actions\Rollups\RulesetSummaries;

class RulesetComparison
{
    public function __construct(private readonly RulesetSummaries $summaries, private readonly ListRulesets $rulesets) {}

    /**
     * Every ruleset with the numbers of the comparison table and its levelling curve.
     *
     * @return array{rulesets: list<array{id: int, slug: string, label: string, hardcore: bool, war_mode: string}>, rows: list<array<string, mixed>>, curves: array<int, list<array{level: int, seconds: float, characters: int}>>}
     */
    public function handle(): array
    {
        $summary = $this->summaries->handle();

        return ['rulesets' => $this->rulesets->handle(), ...$summary];
    }
}
