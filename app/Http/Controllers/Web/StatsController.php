<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\ChooseScope;
use App\Actions\Public\Character\SessionTotals;
use App\Actions\Public\StatsDashboard;
use App\Actions\Public\TalentSpecs;
use App\Actions\Rollups\RulesetSummaries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ScopeRequest;
use App\Models\Character;
use App\Support\PageScope;
use App\Support\Server;
use App\Support\WorldLayer;
use App\View\Pages\Filters;
use App\View\Pages\MapSpec;
use App\View\Pages\StatsCompare;
use App\View\Pages\StatsPage;
use App\View\Pages\TalentSpecPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** The stats dashboard: every ruleset at /stats, one ruleset at /rulesets/{slug}. Both are public. */
class StatsController extends Controller
{
    public function __invoke(ScopeRequest $request, StatsDashboard $dashboard, TalentSpecs $talents, ChooseScope $chooseScope, SessionTotals $sessions, ?string $slug = null): View|RedirectResponse
    {
        $asked = $slug ?? $request->rulesetSlug();
        $answer = $dashboard->handle($asked, $request->serverFilter());
        abort_if($answer === null, 404);

        /** @var PageScope $scope */
        $scope = $answer['scope'];
        if ($asked !== null && $scope->ruleset !== null && $scope->ruleset->slug() !== $asked) {
            $kept = $request->query();
            unset($kept['ruleset']);

            return redirect()->to($slug === null ? $request->fullUrlWithQuery(['ruleset' => $scope->ruleset->slug()]) : url('/rulesets/'.$scope->ruleset->slug()).($kept === [] ? '' : '?'.http_build_query($kept)), 301);
        }
        /** @var list<array{id: int, slug: string, label: string, kind: string|null}> $rulesets */
        $rulesets = $answer['rulesets'];
        $page = StatsPage::data($answer, $scope, RulesetSummaries::TARGET_LEVEL);
        /** @var list<array<string, mixed>> $zones */
        $zones = $answer['map_zones'];

        $query = array_filter(['ruleset' => $slug === null ? $scope->ruleset?->slug() : null, 'server' => $scope->server === Server::All ? null : $scope->server->value]);
        $totals = StatsPage::totals($answer['totals']);
        $switch = $chooseScope->handle($request->viewer(), $request->choice() === 'mine' ? 'everyone' : $request->choice(), $request->comparedName(), false, $query, StatsCompare::everyone($totals), function (int $id) use ($sessions): ?array {
            $character = Character::query()->find($id);

            return $character === null ? null : StatsCompare::character($sessions->handle($id), (int) $character->recorded_seconds);
        });

        return view('pages.stats', [
            'scope' => $switch,
            'path' => $scope->ruleset === null ? '/stats' : '/rulesets/'.$scope->ruleset->slug(),
            'd' => $page,
            'filters' => Filters::for($scope, $rulesets),
            'talents' => TalentSpecPage::block($talents->handle($scope)),
            'map' => MapSpec::density(WorldLayer::Paths, $scope->ruleset?->slug(), $zones),
            'canonical' => $scope->ruleset === null ? '/stats' : '/rulesets/'.$scope->ruleset->slug(),
        ]);
    }
}
