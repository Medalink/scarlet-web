<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\LogoutRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/** POST /logout: end the session and issue a new CSRF token. */
class AuthLogoutController extends Controller
{
    public function __invoke(LogoutRequest $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
