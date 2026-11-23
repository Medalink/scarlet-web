<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\ShowHub;
use App\Http\Controllers\Controller;
use App\View\Hub\Page;
use Illuminate\Contracts\View\View;

/** GET /: the front page, the site's numbers and a way into each part of it. Public. */
class HubController extends Controller
{
    public function __invoke(ShowHub $hub): View
    {
        return view('pages.hub', ['d' => Page::data($hub->handle())]);
    }
}
