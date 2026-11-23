<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The class page and the battlegrounds page of a character are sections of its page: `/c/{name}#class` and `/c/{name}#battlegrounds`. */
class CharacterSectionRedirectController extends Controller
{
    public function __invoke(Request $request, string $name): RedirectResponse
    {
        return redirect('/c/'.rawurlencode($name).'#'.$request->route('anchor'), 301);
    }
}
