<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\ShowImportedHistory;
use App\Actions\Public\CharacterOverview;
use App\Actions\Public\CountBattlegroundMatches;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CharacterRequest;
use App\View\Pages\CharacterPage;
use App\View\Pages\ImportedHistoryPage;
use Illuminate\Contracts\View\View;

/**
 * A character's page. Public when its owner made it public; a private character answers 404 to everyone but its owner.
 * `?as=visitor` renders it with no viewer, so an owner sees exactly what a signed-out visitor sees.
 */
class CharacterController extends Controller
{
    public function __invoke(CharacterRequest $request, CharacterOverview $overview, ShowImportedHistory $imported, CountBattlegroundMatches $matches, string $name): View
    {
        $viewer = $request->viewerAccountId();
        $answer = $overview->handle($name, $request->asVisitor() ? null : $viewer, $request->history(), (bool) config('scarlet.addon_counts_pages'));
        abort_if($answer === null, 404);

        $d = CharacterPage::data($answer);
        $d['ownerSeesVisitorView'] = $request->asVisitor() && $viewer !== null && $answer['character']->account_id === $viewer;
        $d['matches'] = $matches->handle($answer['character']->id);
        $history = $answer['owner'] ? ($imported->handle([$answer['character']->id])[$answer['character']->id] ?? null) : null;
        $d['imported'] = $history === null ? null : ImportedHistoryPage::character($history);

        return view('pages.character', ['d' => $d]);
    }
}
