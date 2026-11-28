<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\ShowInstallStorage;
use App\Actions\Account\ShowStorage;
use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;

/** GET /account/storage: what the account has stored, by character, sharing group and install, the uploads of the last day and the removal of everything. */
class AccountStorageController extends Controller
{
    public function __invoke(Request $request, ShowStorage $storage, ShowInstallStorage $installs): ViewContract
    {
        $account = $request->user();
        abort_unless($account instanceof Account, 403);

        return view('account.storage', ['storage' => $storage->handle($account), 'installs' => $installs->handle($account)]);
    }
}
