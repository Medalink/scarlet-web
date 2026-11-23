<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\ShowLatestSession;
use App\Actions\Public\ShowHub;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\View\Format;
use App\View\Hub\Page;
use App\View\Sc\ClassColor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** GET /: the front page, the site's numbers and a way into each part of it. Public; a signed-in account also sees its latest session. */
class HubController extends Controller
{
    public function __invoke(Request $request, ShowHub $hub, ShowLatestSession $latest): View
    {
        $account = $request->user();
        $session = $account instanceof Account ? $latest->handle($account) : null;

        return view('pages.hub', [
            'd' => Page::data($hub->handle()),
            'signedIn' => $account instanceof Account,
            'latest' => $session === null ? null : [
                'recap' => $session['recap'],
                'name' => Format::characterName($session['name']),
                'color' => ClassColor::of($session['class']),
                'href' => '/c/'.rawurlencode($session['name']).'/sessions/'.$session['recap']['start'],
            ],
        ]);
    }
}
