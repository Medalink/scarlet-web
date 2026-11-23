<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\LinkInstall;
use App\Actions\Account\LinkState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\LinkRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;

/** POST /link: redeem the code the person typed for the signed-in account, when it matches the app's signature. */
class LinkConfirmController extends Controller
{
    public function __invoke(LinkRequest $request, LinkInstall $link): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof Account, 403);

        $redeem = $request->redeem();
        $again = redirect('/link'.($redeem === null ? '' : '?'.http_build_query(['redeem' => $redeem])));

        $code = $request->code();
        if ($code === null) {
            return $again->with('link_message', 'Type the code the Scarlet app shows.');
        }

        $state = $link->handle($account, $code, (string) $redeem);
        if ($state !== LinkState::Linked) {
            return $again->with('link_message', LinkMessage::of($state));
        }

        return redirect('/account')->with('notice', 'The app is linked to your account. It will pick up the link the next time it checks.');
    }
}
