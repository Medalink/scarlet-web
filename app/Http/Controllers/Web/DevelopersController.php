<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\View\Pages\DevelopersPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** The public API reference, read from App\Support\PublicApiCatalog. Public. */
class DevelopersController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('pages.developers', ['d' => DevelopersPage::data($request->getSchemeAndHttpHost())]);
    }
}
