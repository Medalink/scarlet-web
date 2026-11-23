<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\ChooseComparedCharacter;
use App\Actions\Account\OwnCharacterIds;
use App\Actions\Account\OwnCharacterNames;
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
use App\View\Pages\ScopeCompare;
use App\View\Pages\Trades;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The camps dashboard: how players use the campsite. Public at `/camps`; at `/account/camps` the same page over only the
 * signed-in account's characters.
 */
class CampsController extends Controller
{
    public function __invoke(ScopeRequest $request, CampsDashboard $dashboard, MapZones $mapZones, OwnCharacterIds $ownIds, OwnCharacterNames $ownNames, ChooseComparedCharacter $compared): View|RedirectResponse
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

        $path = $own ? '/account/camps' : '/camps';
        $query = array_filter(['ruleset' => $scope->ruleset?->slug(), 'server' => $scope->server === Server::All ? null : $scope->server->value]);
        $names = $request->user() === null ? [] : $ownNames->handle($request->account());
        $choice = $request->choice();
        $chosen = $choice === 'compare' ? $compared->handle($request->account(), $request->comparedName()) : null;
        $versus = null;
        if ($chosen !== null) {
            $yours = $dashboard->handle($asked, $request->serverFilter(), [$chosen['id']]);
            $versus = $yours === null ? null : ScopeCompare::figures($page['figures'], CampsPage::data($yours, null)['figures'], $chosen['name'], ['Characters']);
        }

        return view('pages.camps', [
            'd' => $page,
            'own' => $own,
            'trades' => $own ? Trades::of(Trades::CAMPS, $query === [] ? '' : '?'.http_build_query($query), true) : [],
            'filters' => Filters::onPage($scope, $rulesets, $path),
            'map' => $map,
            'art' => SceneArt::stage(Scene::Camp),
            'scene' => Scene::Camp,
            'canonical' => $path,
            'scope' => ['choice' => $own && $choice !== 'mine' ? 'mine' : $choice, 'names' => $names, 'as' => $chosen['name'] ?? null, 'query' => $query, 'versus' => $versus],
        ]);
    }
}
