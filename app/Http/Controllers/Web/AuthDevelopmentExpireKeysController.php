<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\ExpireApiKeys;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\DevelopmentExpireKeysRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;

/** POST /auth/development/expire-keys: ages the signed-in account's keys of one name past their expiry. Exists only where the development sign-in does. */
class AuthDevelopmentExpireKeysController extends Controller
{
    public function __invoke(DevelopmentExpireKeysRequest $request, ExpireApiKeys $expire): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof Account, 403);
        $expire->handle($account, $request->keyName());

        return redirect('/account/api-keys');
    }
}
