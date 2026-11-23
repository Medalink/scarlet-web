<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\CheckLinkCode;
use App\Actions\Account\DescribeLinkInstall;
use App\Actions\Account\LinkState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\LinkRequest;
use App\Models\Account;
use App\View\Charts;
use Illuminate\Contracts\View\View as ViewContract;

/** GET /link?code=XXXX-XXXX: show the code from the app and ask the signed-in person to confirm it. */
class LinkShowController extends Controller
{
    public function __invoke(LinkRequest $request, CheckLinkCode $check, DescribeLinkInstall $describe): ViewContract
    {
        $account = $request->user();
        abort_unless($account instanceof Account, 403);

        $code = $request->code();
        $state = $code === null ? LinkState::Unknown : $check->handle($account, $code);
        $install = $code === null ? null : $describe->handle($code);

        return view('link.show', [
            'map' => Charts::map(),
            'code' => $code,
            'state' => $state,
            'redeem' => $request->redeem(),
            'install' => $install,
            'fingerprint' => $install === null ? null : substr(hash('sha256', $install->publicKey()), 0, 12),
            'message' => $code === null ? 'Enter the code the Scarlet app shows.' : LinkMessage::of($state),
        ]);
    }
}
