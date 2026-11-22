<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\ClassPage\CharacterClassPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CharacterRequest;
use App\View\Pages\ClassPageData;
use Illuminate\Contracts\View\View;

/** What a character did as its class: the class page. Visible exactly when the character's page is, and only at the character's own class. */
class CharacterClassController extends Controller
{
    public function __invoke(CharacterRequest $request, CharacterClassPage $page, string $name, string $class): View
    {
        $answer = $page->handle($name, $class, $request->viewerAccountId());
        abort_if($answer === null, 404);

        return view('pages.class', ClassPageData::data($answer));
    }
}
