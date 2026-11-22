<?php

namespace App\Actions\Public;

use App\Actions\Rollups\BusiestZones;
use App\Actions\Rollups\WorldHeat;
use App\Support\Server;
use App\Support\WorldLayer;

class WorldMapPage
{
    public function __construct(
        private readonly ResolveScope $resolveScope,
        private readonly ListRulesets $rulesets,
        private readonly WorldHeat $heat,
        private readonly MapZones $zones,
        private readonly BusiestZones $busiest,
    ) {}

    /**
     * What the world map page shows for a ruleset (or all) and a layer. Null when the ruleset is not known.
     *
     * @return array<string, mixed>|null
     */
    public function handle(?string $ruleset, WorldLayer $layer): ?array
    {
        $scope = $this->resolveScope->handle($ruleset, Server::All);
        if ($scope === null) {
            return null;
        }

        return [
            'scope' => $scope,
            'layer' => $layer,
            'rulesets' => $this->rulesets->handle(),
            'heat' => $this->heat->handle($layer, $scope->ids, level: 0),
            'zones' => $this->zones->handle($scope->ids),
            'busiest' => $this->busiest->handle($scope->ids, 8),
        ];
    }
}
