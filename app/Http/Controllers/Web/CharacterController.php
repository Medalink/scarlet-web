<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\ShowImportedHistory;
use App\Actions\Public\CharacterOverview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CharacterRequest;
use App\View\Pages\CharacterPage;
use App\View\Pages\ImportedHistoryPage;
use Illuminate\Contracts\View\View;

/** A character's page. Public when its owner made it public; a private character answers 404 to everyone but its owner. */
class CharacterController extends Controller
{
    public function __invoke(CharacterRequest $request, CharacterOverview $overview, ShowImportedHistory $imported, string $name): View
    {
        $answer = $overview->handle($name, $request->viewerAccountId(), $request->history());
        abort_if($answer === null, 404);

        $d = CharacterPage::data($answer);
        $history = $answer['owner'] ? ($imported->handle([$answer['character']->id])[$answer['character']->id] ?? null) : null;
        $d['imported'] = $history === null ? null : ImportedHistoryPage::character($history);

        return view('pages.character', ['d' => $d]);
    }
}
