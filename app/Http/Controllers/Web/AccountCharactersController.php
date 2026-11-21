<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\ShowCharacterGold;
use App\Actions\Account\ShowCharacterLevelTimes;
use App\Actions\Account\ShowCharacterPlayByDay;
use App\Actions\Account\ShowCharacterProfessions;
use App\Actions\Account\ShowCharacterRoster;
use App\Actions\Account\ShowImportedHistory;
use App\Actions\Account\ShowPlayedTogether;
use App\Actions\Public\ListRulesets;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\View\Pages\AccountCharactersPage;
use App\View\Pages\ImportedHistoryPage;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;

/** GET /account/characters: the signed-in account's characters side by side. Private: it reads only the account's own rows. */
class AccountCharactersController extends Controller
{
    public function __invoke(
        Request $request,
        ShowCharacterRoster $roster,
        ShowCharacterPlayByDay $playByDay,
        ShowCharacterGold $gold,
        ShowPlayedTogether $together,
        ShowCharacterLevelTimes $levelTimes,
        ShowCharacterProfessions $professions,
        ListRulesets $rulesets,
        ShowImportedHistory $imported,
    ): ViewContract {
        $account = $request->user();
        abort_unless($account instanceof Account, 403);
        $characters = $roster->handle($account);
        $ids = array_column($characters, 'id');

        $d = AccountCharactersPage::data(
            $characters,
            $playByDay->handle($ids),
            $gold->handle($ids),
            $together->handle($ids),
            $levelTimes->handle($characters),
            $professions->handle($ids),
            $rulesets->handle(),
        );
        $d['imported'] = ImportedHistoryPage::roster($characters, $imported->handle($ids));

        return view('account.characters', ['d' => $d]);
    }
}
