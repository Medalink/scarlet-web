<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\CharacterBattlegrounds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CharacterRequest;
use App\View\Pages\BattlegroundsPage;
use Illuminate\Contracts\View\View;

/** The scoreboards of a character's latest battlegrounds. Visible exactly when the character's page is. */
class CharacterBattlegroundsController extends Controller
{
    public function __invoke(CharacterRequest $request, CharacterBattlegrounds $battlegrounds, string $name): View
    {
        $answer = $battlegrounds->handle($name, $request->viewerAccountId());
        abort_if($answer === null, 404);

        return view('pages.battlegrounds', ['d' => BattlegroundsPage::data($answer), 'columns' => BattlegroundsPage::COLUMNS]);
    }
}
