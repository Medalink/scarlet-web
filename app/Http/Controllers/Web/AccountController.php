<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View as ViewContract;

/** GET /account: the page where a signed-in person controls their characters, installs, sharing and keys. */
class AccountController extends Controller
{
    public function __invoke(): ViewContract
    {
        return view('account.show');
    }
}
