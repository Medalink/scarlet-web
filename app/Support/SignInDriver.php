<?php

namespace App\Support;

use App\Models\IdentityProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

/**
 * The Socialite driver of one provider, set up the same way for the redirect and for the callback: the
 * `openid` scope only, so the provider returns no email, name or picture, and PKCE where the provider
 * documents it (Google; Battle.net's metadata lists no code challenge method).
 */
class SignInDriver
{
    public function make(IdentityProvider $provider): AbstractProvider
    {
        $driver = Socialite::driver($provider->value);
        assert($driver instanceof AbstractProvider);
        $driver->setScopes(['openid']);

        return $provider === IdentityProvider::Google ? $driver->enablePKCE() : $driver;
    }
}
