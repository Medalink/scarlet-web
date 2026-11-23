<?php

use App\Http\Controllers\Web\AccountCharactersController;
use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\AccountProfessionsController;
use App\Http\Controllers\Web\AuthCallbackController;
use App\Http\Controllers\Web\AuthDevelopmentController;
use App\Http\Controllers\Web\AuthLoginController;
use App\Http\Controllers\Web\AuthLogoutController;
use App\Http\Controllers\Web\AuthRedirectController;
use App\Http\Controllers\Web\CampsController;
use App\Http\Controllers\Web\LinkConfirmController;
use App\Http\Controllers\Web\LinkShowController;
use App\Http\Controllers\Web\MapController;
use App\Http\Controllers\Web\MapTrailsController;
use App\Http\Controllers\Web\ProfessionController;
use App\Http\Requests\Web\ScopeRequest;
use App\Models\IdentityProvider;
use Illuminate\Support\Facades\Route;

// Sign-in: Google and Battle.net by authorization code, the account page, and linking the app.
Route::get('/login', AuthLoginController::class)->name('login')->middleware('throttle:60,1');
Route::get('/auth/{provider}/redirect', AuthRedirectController::class)->middleware('throttle:30,1');
Route::get('/auth/{provider}/callback', AuthCallbackController::class)->middleware('throttle:30,1');
Route::post('/logout', AuthLogoutController::class);

// The development sign-in exists only where the environment is `local` and the switch is on.
if (IdentityProvider::developmentAllowed()) {
    Route::post('/auth/development', AuthDevelopmentController::class)->middleware('throttle:30,1');
}

Route::middleware(['auth', 'throttle:60,1'])->group(function (): void {
    Route::get('/account', AccountController::class);
    Route::get('/account/characters', AccountCharactersController::class);
    Route::get('/account/professions', AccountProfessionsController::class);
    Route::get('/account/camps', CampsController::class)->defaults(ScopeRequest::OWN, true)->middleware(['scarlet.timeout', 'cache.headers:private;no_store']);
    Route::get('/account/professions/{slug}', ProfessionController::class)->where('slug', '[a-z-]+')->defaults(ScopeRequest::OWN, true)->middleware(['scarlet.timeout', 'cache.headers:private;no_store']);
    Route::get('/account/map', MapController::class)->defaults(ScopeRequest::OWN, true)->middleware(['scarlet.timeout', 'cache.headers:private;no_store']);
    Route::get('/account/map/trails', MapTrailsController::class)->defaults(ScopeRequest::OWN, true)->middleware(['scarlet.timeout', 'cache.headers:private;no_store']);
    Route::get('/link', LinkShowController::class);
    Route::post('/link', LinkConfirmController::class);
});
