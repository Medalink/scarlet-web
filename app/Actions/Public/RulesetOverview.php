<?php

namespace App\Actions\Public;

use App\Actions\Rollups\Coverage;
use App\Actions\Rollups\Totals;
use App\Support\PageScope;
use App\Support\Server;

class RulesetOverview
{
    public function __construct(
        private readonly ResolveScope $resolveScope,
        private readonly Totals $totals,
        private readonly Coverage $coverage,
        private readonly ListRulesets $rulesets,
    ) {}

    /**
     * The headline numbers of a ruleset, of a server's rulesets, or of everything, with when the data starts and
     * was last updated. Null when the ruleset is not known.
     *
     * @return array{scope: PageScope, totals: array<string, int|float|null>, coverage: array<string, mixed>, rulesets: list<array<string, mixed>>}|null
     */
    public function handle(?string $ruleset, Server $server = Server::All): ?array
    {
        $scope = $this->resolveScope->handle($ruleset, $server);
        if ($scope === null) {
            return null;
        }

        return [
            'scope' => $scope,
            'totals' => $this->totals->handle($scope->ids),
            'coverage' => $this->coverage->handle(),
            'rulesets' => $this->rulesets->handle(),
        ];
    }
}
