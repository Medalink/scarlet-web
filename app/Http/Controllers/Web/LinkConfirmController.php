<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\LinkInstall;
use App\Actions\Account\LinkState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\LinkRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;

/** POST /link: redeem the code for the signed-in account. */
class LinkConfirmController extends Controller
{
    public function __invoke(LinkRequest $request, LinkInstall $link): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof Account, 403);

        $code = $request->code();
        if ($code === null) {
            return redirect('/link');
        }

        $state = $link->handle($account, $code);
        if ($state !== LinkState::Linked) {
            return redirect('/link?code='.$code);
        }

        return redirect('/account')->with('notice', 'The app is linked to your account. It sees the link the next time it checks.');
    }
}
