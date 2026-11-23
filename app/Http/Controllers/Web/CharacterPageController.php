<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\ShowCharacter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CharacterRequest;
use App\View\Character\Page;
use Illuminate\Contracts\View\View;

/** A character's page as one page: overview, feats, sessions, class, levelling, combat, loot and play time. */
class CharacterPageController extends Controller
{
    public function __invoke(CharacterRequest $request, ShowCharacter $show, string $name): View
    {
        $bundle = $show->handle($name, $request->asVisitor() ? null : $request->viewerAccountId(), $request->sessions(), (bool) config('scarlet.addon_counts_pages'));
        abort_if($bundle === null, 404);

        return view('character.page', ['d' => Page::data($bundle, $request->asVisitor(), $request->viewerAccountId())]);
    }
}
