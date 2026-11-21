<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\StatsDashboard;
use App\Actions\Public\TalentSpecs;
use App\Actions\Rollups\RulesetSummaries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ScopeRequest;
use App\Support\PageScope;
use App\View\Pages\Filters;
use App\View\Pages\MapSpec;
use App\View\Pages\StatsPage;
use App\View\Pages\TalentSpecPage;
use Illuminate\Contracts\View\View;

/** The stats dashboard: every ruleset at /stats, one ruleset at /rulesets/{slug}. Both are public. */
class StatsController extends Controller
{
    public function __invoke(ScopeRequest $request, StatsDashboard $dashboard, TalentSpecs $talents, ?string $slug = null): View
    {
        $answer = $dashboard->handle($slug ?? $request->rulesetSlug(), $request->serverFilter());
        abort_if($answer === null, 404);

        /** @var PageScope $scope */
        $scope = $answer['scope'];
        /** @var list<array{id: int, slug: string, label: string, kind: string|null}> $rulesets */
        $rulesets = $answer['rulesets'];
        $page = StatsPage::data($answer, $scope, RulesetSummaries::TARGET_LEVEL);
        /** @var list<array<string, mixed>> $heat */
        $heat = $answer['heat'];
        /** @var list<array<string, mixed>> $zones */
        $zones = $answer['map_zones'];

        return view('pages.stats', [
            'd' => $page,
            'filters' => Filters::for($scope, $rulesets),
            'talents' => TalentSpecPage::block($talents->handle($scope)),
            'map' => MapSpec::build($heat, $zones, extra: ['ruleset' => $scope->ruleset?->slug()]),
        ]);
    }
}
