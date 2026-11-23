<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\ChooseScope;
use App\Actions\Account\OwnCharacterIds;
use App\Actions\Public\CampsDashboard;
use App\Actions\Public\MapZones;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ScopeRequest;
use App\Models\MapRect;
use App\Support\PageScope;
use App\Support\Scene;
use App\Support\SceneArt;
use App\Support\Server;
use App\Support\WorldLayer;
use App\View\Pages\CampsPage;
use App\View\Pages\Filters;
use App\View\Pages\MapSpec;
use App\View\Pages\Trades;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * The camps dashboard: how players use the campsite. Public at `/camps`; with `?scope=mine` the same page over only the
 * signed-in account's characters.
 */
class CampsController extends Controller
{
    public function __invoke(ScopeRequest $request, CampsDashboard $dashboard, MapZones $mapZones, OwnCharacterIds $ownIds, ChooseScope $chooseScope): Response|RedirectResponse
    {
        $characters = $request->ownCharacters($ownIds);
        $own = $characters !== null;
        $asked = $request->rulesetSlug();
        $answer = $dashboard->handle($asked, $request->serverFilter(), $characters);
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
        $map = $page['dots'] === null ? null : MapSpec::density(WorldLayer::Paths, $scope->ruleset?->slug(), $mapZones->handle($scope->rollup()), dots: $page['dots']);

        $path = '/camps';
        $query = array_filter(['ruleset' => $scope->ruleset?->slug(), 'server' => $scope->server === Server::All ? null : $scope->server->value]);
        $switch = $chooseScope->handle($request->viewer(), $request->choice(), $request->comparedName(), $own, $query, $page['figures'], function (int $id) use ($dashboard, $asked, $request): ?array {
            $yours = $dashboard->handle($asked, $request->serverFilter(), [$id]);

            return $yours === null ? null : CampsPage::data($yours, null)['figures'];
        }, ['Characters']);

        return response()->view('pages.camps', [
            'd' => $page,
            'own' => $own,
            'trades' => $own ? Trades::of(Trades::CAMPS, '?'.http_build_query($query + ['scope' => 'mine']), true) : [],
            'filters' => Filters::onPage($scope, $rulesets, $path, $own ? ['scope' => 'mine'] : []),
            'map' => $map,
            'art' => SceneArt::stage(Scene::Camp),
            'scene' => Scene::Camp,
            'canonical' => $path,
            'scope' => $switch,
        ], 200, $own ? ['Cache-Control' => ScopeRequest::PRIVATE_CACHE] : []);
    }
}
