<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View as ViewContract;

/** GET /account/privacy: what each character shows on its public page and what the app sends. */
class AccountPrivacyController extends Controller
{
    public function __invoke(): ViewContract
    {
        return view('account.privacy');
    }
}
