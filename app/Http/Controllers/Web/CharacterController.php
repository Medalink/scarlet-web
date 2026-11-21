<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\CharacterOverview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CharacterRequest;
use App\View\Pages\CharacterPage;
use Illuminate\Contracts\View\View;

/** A character's page. Public when its owner made it public; a private character answers 404 to everyone but its owner. */
class CharacterController extends Controller
{
    public function __invoke(CharacterRequest $request, CharacterOverview $overview, string $name): View
    {
        $answer = $overview->handle($name, $request->viewerAccountId(), $request->history());
        abort_if($answer === null, 404);

        return view('pages.character', ['d' => CharacterPage::data($answer)]);
    }
}
