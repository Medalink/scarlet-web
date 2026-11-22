<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\CampsDashboard;
use App\Actions\Public\MapZones;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ScopeRequest;
use App\Models\MapRect;
use App\Support\CampArt;
use App\Support\PageScope;
use App\Support\WorldLayer;
use App\View\Pages\CampsPage;
use App\View\Pages\Filters;
use App\View\Pages\MapSpec;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** The camps dashboard: how players use the campsite. Public. */
class CampsController extends Controller
{
    public function __invoke(ScopeRequest $request, CampsDashboard $dashboard, MapZones $mapZones): View|RedirectResponse
    {
        $asked = $request->rulesetSlug();
        $answer = $dashboard->handle($asked, $request->serverFilter());
        abort_if($answer === null, 404);

        /** @var PageScope $scope */
        $scope = $answer['scope'];
        if ($asked !== null && $scope->ruleset !== null && $scope->ruleset->slug() !== $asked) {
            return redirect()->to($request->fullUrlWithQuery(['ruleset' => $scope->ruleset->slug()]), 301);
        }
        /** @var list<array{id: int, slug: string, label: string, kind: string|null}> $rulesets */
        $rulesets = $answer['rulesets'];
        $rect = is_array($answer['heat'] ?? null) ? MapRect::query()->find((int) $answer['heat']['map']) : null;
        $page = CampsPage::data($answer, $rect);
        $map = $page['dots'] === null ? null : MapSpec::density(WorldLayer::Paths, $scope->ruleset?->slug(), $mapZones->handle($scope->ids), dots: $page['dots']);
        $art = CampArt::state();

        return view('pages.camps', [
            'd' => $page,
            'filters' => Filters::onPage($scope, $rulesets, '/camps'),
            'map' => $map,
            'art' => $art === null ? null : ['models' => "/camp-art/camp.json?v={$art['version']}", 'texture' => "/camp-art/tex/{id}.png?v={$art['version']}"],
            'canonical' => '/camps',
        ]);
    }
}
