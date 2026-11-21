<?php

namespace App\Http\Controllers\Web;

use App\Actions\Auth\SignIn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\DevelopmentSignInRequest;
use App\Models\IdentityProvider;
use Illuminate\Http\RedirectResponse;

/** POST /auth/development: signs in as the fixed development identity through the Action the callback uses. */
class AuthDevelopmentController extends Controller
{
    public function __invoke(DevelopmentSignInRequest $request, SignIn $signIn, AuthCompletion $completion): RedirectResponse
    {
        $result = $signIn->handle(IdentityProvider::Development, SignIn::DEVELOPMENT_SUBJECT, $request->user());

        return $completion->finish($request, IdentityProvider::Development, $result);
    }
}
