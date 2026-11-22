<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\GatheringDashboard;
use App\Actions\Public\MapZones;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ScopeRequest;
use App\Models\MapRect;
use App\Support\PageScope;
use App\Support\Profession;
use App\Support\SceneArt;
use App\Support\Server;
use App\Support\WorldLayer;
use App\View\Pages\Filters;
use App\View\Pages\GatheringPage;
use App\View\Pages\MapSpec;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** The page of one gathering profession: what players gather and how. Public. */
class ProfessionController extends Controller
{
    public function __invoke(ScopeRequest $request, GatheringDashboard $dashboard, MapZones $mapZones, string $slug): View|RedirectResponse
    {
        $kind = Profession::fromSlug($slug)?->gatherKind();
        abort_if($kind === null, 404);

        $asked = $request->rulesetSlug();
        $answer = $dashboard->handle($kind, $asked, $request->serverFilter());
        abort_if($answer === null, 404);

        /** @var PageScope $scope */
        $scope = $answer['scope'];
        if ($asked !== null && $scope->ruleset !== null && $scope->ruleset->slug() !== $asked) {
            return redirect()->to($request->fullUrlWithQuery(['ruleset' => $scope->ruleset->slug()]), 301);
        }
        /** @var list<array{id: int, slug: string, label: string, kind: string|null}> $rulesets */
        $rulesets = $answer['rulesets'];
        $rect = is_array($answer['heat'] ?? null) ? MapRect::query()->find((int) $answer['heat']['map']) : null;
        $page = GatheringPage::data($kind, $answer, $rect);
        $map = $page['dots'] === null ? null : MapSpec::density(WorldLayer::Paths, $scope->ruleset?->slug(), $mapZones->handle($scope->ids), dots: $page['dots']);
        $path = '/professions/'.$slug;
        $query = array_filter(['ruleset' => $scope->ruleset?->slug(), 'server' => $scope->server === Server::All ? null : $scope->server->value]);

        return view('pages.profession', [
            'd' => $page,
            'scene' => $kind->scene(),
            'art' => SceneArt::stage($kind->scene()),
            'filters' => Filters::onPage($scope, $rulesets, $path),
            'trades' => $this->trades($slug, $query === [] ? '' : '?'.http_build_query($query)),
            'map' => $map,
            'title' => $page['words']['title'].': '.$page['words']['results'].', times and places in WoW Forever',
            'canonical' => $path,
        ]);
    }

    /** @return list<array{label: string, href: string, current: bool}> the professions that have a page, each linked with the scope kept */
    private function trades(string $slug, string $query): array
    {
        $trades = [];
        foreach (Profession::withPages() as $profession) {
            if ($profession->gatherKind() !== null) {
                $trades[] = ['label' => $profession->value, 'href' => '/professions/'.$profession->slug().$query, 'current' => $profession->slug() === $slug];
            }
        }

        return $trades;
    }
}
