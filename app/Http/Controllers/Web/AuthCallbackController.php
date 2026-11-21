<?php

namespace App\Http\Controllers\Web;

use App\Actions\Auth\SignIn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\OAuthCallbackRequest;
use App\Models\IdentityProvider;
use App\Support\SignInDriver;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Two\InvalidStateException;

/**
 * GET /auth/{provider}/callback: check the state, trade the code for the provider's answer and hand only the
 * provider and the subject id to the sign-in Action. Nothing else the provider returns is kept.
 */
class AuthCallbackController extends Controller
{
    public function __invoke(OAuthCallbackRequest $request, IdentityProvider $provider, SignInDriver $drivers, SignIn $signIn, AuthCompletion $completion): RedirectResponse
    {
        abort_unless($provider->isOAuth() && $provider->isConfigured(), 404);

        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect('/login')->with('notice', "{$provider->label()} sign-in was cancelled.");
        }

        try {
            $subject = (string) $drivers->make($provider)->user()->getId();
        } catch (InvalidStateException|GuzzleException) {
            return redirect('/login')->with('notice', "{$provider->label()} sign-in failed. Try again.");
        }

        return $completion->finish($request, $provider, $signIn->handle($provider, $subject, $request->user()));
    }
}
