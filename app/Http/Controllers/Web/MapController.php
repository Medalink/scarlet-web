<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\WorldMapPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\MapRequest;
use App\View\Pages\MapPage;
use Illuminate\Contracts\View\View;

/** The world map: heat from paths, deaths and creatures on the world's sheet or a zone's. Public. */
class MapController extends Controller
{
    public function __invoke(MapRequest $request, WorldMapPage $page): View
    {
        $answer = $page->handle($request->rulesetSlug(), $request->layer());
        abort_if($answer === null, 404);

        return view('pages.map', ['d' => MapPage::data($answer, $request->zone())]);
    }
}
