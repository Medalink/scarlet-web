<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\CharacterBattlegrounds;
use App\Actions\Public\ClassPage\CharacterLinks;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CharacterRequest;
use App\View\Format;
use App\View\Pages\BattlegroundsPage;
use Illuminate\Contracts\View\View;

/** The scoreboards of a character's latest battlegrounds. Visible exactly when the character's page is. */
class CharacterBattlegroundsController extends Controller
{
    public function __invoke(CharacterRequest $request, CharacterBattlegrounds $battlegrounds, CharacterLinks $links, string $name): View
    {
        $answer = $battlegrounds->handle($name, $request->viewerAccountId());
        abort_if($answer === null, 404);
        $d = BattlegroundsPage::data($answer);
        $d['links'] = $links->handle($answer['character']);
        $d['className'] = Format::className($answer['character']->class);

        return view('pages.battlegrounds', ['d' => $d, 'columns' => BattlegroundsPage::COLUMNS]);
    }
}
