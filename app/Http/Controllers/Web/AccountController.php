<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\ShowAccountHome;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\View\Account\Home;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;

/** GET /account: the signed-in person's characters, their latest session, their week and where they stand. */
class AccountController extends Controller
{
    public function __invoke(Request $request, ShowAccountHome $home): ViewContract
    {
        $account = $request->user();
        abort_unless($account instanceof Account, 403);

        return view('account.home', ['d' => Home::data($home->handle($account))]);
    }
}
