<?php

namespace App\Models;

/** The ways to sign in. `Development` exists only in a local environment with `scarlet.dev_login` on. */
enum IdentityProvider: string
{
    case Google = 'google';
    case Battlenet = 'battlenet';
    case Development = 'development';

    /** The providers a player can use on a real server, in the order the page lists them. */
    public const array REAL = [self::Google, self::Battlenet];

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Battlenet => 'Battle.net',
            self::Development => 'Development',
        };
    }

    public function isOAuth(): bool
    {
        return $this !== self::Development;
    }

    /** True when the server has a client id for the provider. */
    public function isConfigured(): bool
    {
        if (! $this->isOAuth()) {
            return self::developmentAllowed();
        }

        return filled(config('services.'.$this->value.'.client_id'));
    }

    /** The one check that lets the development sign-in exist: a local environment and the switch on. */
    public static function developmentAllowed(): bool
    {
        return app()->environment('local') && config('scarlet.dev_login') === true;
    }
}
