<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\IdentityProvider;
use App\View\Charts;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** GET /login: the sign-in page. A provider with no client id on this server is shown disabled. */
class AuthLoginController extends Controller
{
    public function __invoke(Request $request): ViewContract|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect('/account');
        }

        $providers = [];
        foreach (IdentityProvider::REAL as $provider) {
            $providers[] = [
                'label' => 'Continue with '.$provider->label(),
                'icon' => $provider->value,
                'href' => '/auth/'.$provider->value.'/redirect',
                'disabled' => ! $provider->isConfigured(),
            ];
        }
        if (IdentityProvider::developmentAllowed()) {
            $providers[] = ['label' => 'Development sign-in', 'icon' => IdentityProvider::Development->value, 'form' => '/auth/development'];
        }

        return view('auth.login', ['providers' => $providers, 'map' => Charts::map()]);
    }
}
