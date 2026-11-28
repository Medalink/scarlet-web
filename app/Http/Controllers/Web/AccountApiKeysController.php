<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View as ViewContract;

/** GET /account/api-keys: the account's API keys. */
class AccountApiKeysController extends Controller
{
    public function __invoke(): ViewContract
    {
        return view('account.api-keys');
    }
}
