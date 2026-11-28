<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\DropBattlenetIdentity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\DevelopmentSignInRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;

/** POST /auth/development/drop-battlenet: removes the signed-in account's Battle.net sign-in and keeps its keys. Exists only where the development sign-in does. */
class AuthDevelopmentDropBattlenetController extends Controller
{
    public function __invoke(DevelopmentSignInRequest $request, DropBattlenetIdentity $drop): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof Account, 403);
        $drop->handle($account);

        return redirect('/account/settings');
    }
}
