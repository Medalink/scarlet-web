<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View as ViewContract;

/** GET /account/settings: where a signed-in person controls what is shared, their installs, sign-in methods, keys and uploads. */
class AccountSettingsController extends Controller
{
    public function __invoke(): ViewContract
    {
        return view('account.settings');
    }
}
