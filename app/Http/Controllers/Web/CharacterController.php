<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\ShowImportedHistory;
use App\Actions\Public\CharacterOverview;
use App\Actions\Public\ClassPage\CharacterClassBand;
use App\Actions\Public\ClassPage\CharacterLinks;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CharacterRequest;
use App\Support\ClassPage\ClassIcons;
use App\View\Format;
use App\View\Pages\CharacterPage;
use App\View\Pages\ImportedHistoryPage;
use Illuminate\Contracts\View\View;

/**
 * A character's page. Public when its owner made it public; a private character answers 404 to everyone but its owner.
 * `?as=visitor` renders it with no viewer, so an owner sees exactly what a signed-out visitor sees.
 */
class CharacterController extends Controller
{
    public function __invoke(CharacterRequest $request, CharacterOverview $overview, ShowImportedHistory $imported, CharacterLinks $links, CharacterClassBand $classBand, string $name): View
    {
        $viewer = $request->viewerAccountId();
        $answer = $overview->handle($name, $request->asVisitor() ? null : $viewer, $request->history(), (bool) config('scarlet.addon_counts_pages'));
        abort_if($answer === null, 404);
        $character = $answer['character'];

        $d = CharacterPage::data($answer);
        $d['ownerSeesVisitorView'] = $request->asVisitor() && $viewer !== null && $character->account_id === $viewer;
        $d['links'] = $links->handle($character);
        $d['className'] = Format::className($character->class);
        $d['classBand'] = $classBand->handle($character);
        $d['classIcons'] = $d['classBand'] === null ? null : ClassIcons::of($d['classBand']);
        $history = $answer['owner'] ? ($imported->handle([$character->id])[$character->id] ?? null) : null;
        $d['imported'] = $history === null ? null : ImportedHistoryPage::character($history);

        return view('pages.character', ['d' => $d]);
    }
}
