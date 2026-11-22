<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\IdentityProvider;
use App\Support\SignInDriver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** GET /auth/{provider}/redirect: send the browser to the provider with a state check and, for Google, a PKCE challenge. */
class AuthRedirectController extends Controller
{
    public function __invoke(Request $request, IdentityProvider $provider, SignInDriver $drivers): Response
    {
        abort_unless($provider->isOAuth(), 404);
        abort_unless($provider->isConfigured(), 503, "{$provider->label()} sign-in is not set up on this server.");

        return $drivers->make($provider)->redirect();
    }
}
