<?php

namespace App\Http\Controllers\Web;

use App\Actions\Sessions\ShowSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CharacterRequest;
use App\View\Pages\SessionPage;
use Illuminate\Contracts\View\View;

/**
 * The page of one session of a character. Public when its owner made the character public and kept the session pages on; any other viewer gets a 404,
 * and `?as=visitor` shows the owner what a visitor sees.
 */
class CharacterSessionController extends Controller
{
    public function __invoke(CharacterRequest $request, ShowSession $show, string $name, int $start): View
    {
        $answer = $show->handle($name, $start, $request->asVisitor() ? null : $request->viewerAccountId());
        abort_if($answer === null, 404);

        return view('pages.session', ['d' => SessionPage::data($answer)]);
    }
}
