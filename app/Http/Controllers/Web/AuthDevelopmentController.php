<?php

namespace App\Http\Controllers\Web;

use App\Actions\Auth\SignIn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\DevelopmentSignInRequest;
use App\Models\IdentityProvider;
use Illuminate\Http\RedirectResponse;

/**
 * POST /auth/development: signs in as the fixed development identity through the Action the callback uses. With
 * `as=battlenet` it signs in, or links to the signed-in account, the fixed development Battle.net identity instead.
 */
class AuthDevelopmentController extends Controller
{
    public function __invoke(DevelopmentSignInRequest $request, SignIn $signIn, AuthCompletion $completion): RedirectResponse
    {
        $provider = $request->asBattlenet() ? IdentityProvider::Battlenet : IdentityProvider::Development;
        $subject = $request->asBattlenet() ? SignIn::DEVELOPMENT_BATTLENET_SUBJECT : SignIn::DEVELOPMENT_SUBJECT;

        return $completion->finish($request, $provider, $signIn->handle($provider, $subject, $request->user()));
    }
}
