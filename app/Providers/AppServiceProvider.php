<?php

namespace App\Providers;

use App\Support\RollupRead;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Battlenet\BattlenetExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(RollupRead::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sign in with Battle.net: the Socialite provider registers itself only when this listener is attached.
        Event::listen(SocialiteWasCalled::class, BattlenetExtendSocialite::class);
        Event::listen(DiagnosingHealth::class, fn () => DB::select('select 1'));
    }
}
