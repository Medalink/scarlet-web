<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\OwnCharacterNames;
use App\Actions\Public\PersonalMapPage;
use App\Actions\Public\WorldMapPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\MapRequest;
use App\View\Pages\MapPage;
use App\View\Pages\PersonalMap;
use Illuminate\Contracts\View\View;

/**
 * The world map: heat from paths, deaths and creatures on the world, opened on a zone when the address names one. Public.
 * With `?character=` it is one character's trails, for every viewer who may see that character's page, and at
 * `/account/map` the trails of every character of the signed-in account.
 */
class MapController extends Controller
{
    public function __invoke(MapRequest $request, WorldMapPage $page, PersonalMapPage $personal, OwnCharacterNames $ownNames): View
    {
        $viewer = $request->viewer();
        if ($request->own() || $request->character() !== null) {
            $answer = $personal->handle($request->own() ? null : $request->character(), $viewer);
            abort_if($answer === null, 404);

            return view('pages.map', ['d' => PersonalMap::data($answer, $request->zone())]);
        }

        $answer = $page->handle($request->rulesetSlug(), $request->layer());
        abort_if($answer === null, 404);

        return view('pages.map', ['d' => MapPage::data($answer, $request->zone(), $viewer === null ? null : $ownNames->handle($viewer))]);
    }
}
