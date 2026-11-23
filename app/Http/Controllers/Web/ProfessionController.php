<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\ChooseScope;
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * The page of one profession, gathering or crafting: what players do with it and how. Public at `/professions/{slug}`;
 * with `?scope=mine` the same page over only the signed-in account's characters.
 */
class ProfessionController extends Controller
{
    public function __invoke(ScopeRequest $request, GatheringDashboard $gathering, CraftingDashboard $crafting, MapZones $mapZones, OwnCharacterIds $ownIds, ChooseScope $chooseScope, string $slug): Response|RedirectResponse
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
            : $gathering->handle($kind, $asked, $request->serverFilter(), $characters, (bool) config('scarlet.addon_counts_pages'));
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
        $path = '/professions/'.$slug;
        $query = array_filter(['ruleset' => $scope->ruleset?->slug(), 'server' => $scope->server === Server::All ? null : $scope->server->value]);
        $switch = $chooseScope->handle($request->viewer(), $request->choice(), $request->comparedName(), $own, $query, $page['figures'], function (int $id) use ($kind, $profession, $gathering, $crafting, $asked, $request): ?array {
            $yours = $kind === null
                ? $crafting->handle($profession, $asked, $request->serverFilter(), [$id])
                : $gathering->handle($kind, $asked, $request->serverFilter(), [$id], (bool) config('scarlet.addon_counts_pages'));
            if ($yours === null) {
                return null;
            }

            return $kind === null ? CraftingPage::data($profession, $yours)['figures'] : GatheringPage::data($kind, $yours, null)['figures'];
        }, ['Characters', 'Crafters', 'Gatherers']);

        return response()->view('pages.profession', [
            'scope' => $switch,
            'd' => $page,
            'scene' => $scene,
            'art' => SceneArt::stage($scene),
            'filters' => Filters::onPage($scope, $rulesets, $path, $own ? ['scope' => 'mine'] : []),
            'trades' => Trades::of($slug, '?'.http_build_query($own ? $query + ['scope' => 'mine'] : $query), $own),
            'own' => $own,
            'slug' => $slug,
            'everyone' => '/professions/'.$slug,
            'map' => $map,
            'title' => $page['title'],
            'canonical' => $path,
        ], 200, $own ? ['Cache-Control' => ScopeRequest::PRIVATE_CACHE] : []);
    }
}
