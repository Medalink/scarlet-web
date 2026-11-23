<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\LinkRequest;
use App\Models\Account;
use App\View\Charts;
use Illuminate\Contracts\View\View as ViewContract;

/**
 * GET /link?redeem=...: ask the signed-in person to type the code their Scarlet app shows. The address carries
 * the app's signature only; a code in it is ignored, so a link someone else made cannot fill the code in.
 */
class LinkShowController extends Controller
{
    public function __invoke(LinkRequest $request): ViewContract
    {
        abort_unless($request->user() instanceof Account, 403);

        $message = session('link_message');

        return view('link.show', [
            'map' => Charts::map(),
            'redeem' => $request->redeem(),
            'message' => is_string($message) ? $message : null,
        ]);
    }
}
