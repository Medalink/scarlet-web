<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\OwnCharacterIds;
use App\Actions\Public\CraftingDashboard;
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
use App\View\Pages\CraftingPage;
use App\View\Pages\Filters;
use App\View\Pages\GatheringPage;
use App\View\Pages\MapSpec;
use App\View\Pages\Trades;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The page of one profession, gathering or crafting: what players do with it and how. Public at `/professions/{slug}`;
 * at `/account/professions/{slug}` the same page over only the signed-in account's characters.
 */
class ProfessionController extends Controller
{
    public function __invoke(ScopeRequest $request, GatheringDashboard $gathering, CraftingDashboard $crafting, MapZones $mapZones, OwnCharacterIds $ownIds, string $slug): View|RedirectResponse
    {
        $profession = Profession::fromSlug($slug);
        $scene = $profession?->scene();
        abort_if($profession === null || $scene === null, 404);

        $characters = $request->ownCharacters($ownIds);
        $own = $characters !== null;
        $asked = $request->rulesetSlug();
        $kind = $profession->gatherKind();
        $answer = $kind === null
            ? $crafting->handle($profession, $asked, $request->serverFilter(), $characters)
            : $gathering->handle($kind, $asked, $request->serverFilter(), $characters);
        abort_if($answer === null, 404);

        /** @var PageScope $scope */
        $scope = $answer['scope'];
        if ($asked !== null && $scope->ruleset !== null && $scope->ruleset->slug() !== $asked) {
            return redirect()->to($request->fullUrlWithQuery(['ruleset' => $scope->ruleset->slug()]), 301);
        }
        /** @var list<array{id: int, slug: string, label: string, kind: string|null}> $rulesets */
        $rulesets = $answer['rulesets'];
        $rect = is_array($answer['heat'] ?? null) ? MapRect::query()->find((int) $answer['heat']['map']) : null;
        $page = $kind === null ? CraftingPage::data($profession, $answer) : GatheringPage::data($kind, $answer, $rect);
        $map = $page['dots'] === null ? null : MapSpec::density(WorldLayer::Paths, $scope->ruleset?->slug(), $mapZones->handle($scope->rollup()), dots: $page['dots']);
        $path = ($own ? '/account' : '').'/professions/'.$slug;
        $query = array_filter(['ruleset' => $scope->ruleset?->slug(), 'server' => $scope->server === Server::All ? null : $scope->server->value]);

        return view('pages.profession', [
            'd' => $page,
            'scene' => $scene,
            'art' => SceneArt::stage($scene),
            'filters' => Filters::onPage($scope, $rulesets, $path),
            'trades' => Trades::of($slug, $query === [] ? '' : '?'.http_build_query($query), $own),
            'own' => $own,
            'everyone' => '/professions/'.$slug,
            'map' => $map,
            'title' => $page['title'],
            'canonical' => $path,
        ]);
    }
}
