<?php

namespace App\Http\Controllers\Web;

use App\Actions\Auth\SignInOutcome;
use App\Actions\Auth\SignInResult;
use App\Models\IdentityProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** The end of every sign-in, real or development: start the session, or say why not. */
class AuthCompletion
{
    public function finish(Request $request, IdentityProvider $provider, SignInResult $result): RedirectResponse
    {
        $name = $provider->label();

        return match ($result->outcome) {
            SignInOutcome::SignedIn => $this->start($request, $result),
            SignInOutcome::Linked => redirect('/account')->with('notice', "{$name} is linked to your account."),
            SignInOutcome::Taken => redirect('/account')->with('notice', "That {$name} sign-in belongs to another account. Sign out and use it there, or link a different one."),
            SignInOutcome::OtherSubject => redirect('/account')->with('notice', "Your account already has a different {$name} sign-in. Unlink it first."),
            SignInOutcome::Refused => redirect('/login')->with('notice', "{$name} did not return an id. Try again."),
        };
    }

    private function start(Request $request, SignInResult $result): RedirectResponse
    {
        Auth::login($result->account ?? abort(500));
        $request->session()->regenerate();

        return redirect()->intended('/account');
    }
}
