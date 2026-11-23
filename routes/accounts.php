<?php

use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\AccountSettingsController;
use App\Http\Controllers\Web\AuthCallbackController;
use App\Http\Controllers\Web\AuthDevelopmentController;
use App\Http\Controllers\Web\AuthLoginController;
use App\Http\Controllers\Web\AuthLogoutController;
use App\Http\Controllers\Web\AuthRedirectController;
use App\Http\Controllers\Web\LegacyRedirectController;
use App\Http\Controllers\Web\LinkConfirmController;
use App\Http\Controllers\Web\LinkShowController;
use App\Models\IdentityProvider;
use Illuminate\Support\Facades\Route;

// Sign-in: Google and Battle.net by authorization code, the account page, and linking the app.
Route::get('/login', AuthLoginController::class)->name('login')->middleware('throttle:60,1');
Route::get('/auth/{provider}/redirect', AuthRedirectController::class)->middleware('throttle:30,1');
Route::get('/auth/{provider}/callback', AuthCallbackController::class)->middleware('throttle:30,1');
Route::post('/logout', AuthLogoutController::class);

// The development sign-in exists only where the environment is `local` and the switch is on.
if (IdentityProvider::developmentAllowed()) {
    Route::post('/auth/development', AuthDevelopmentController::class)->middleware('throttle:120,1');
}

Route::get('/account/characters', LegacyRedirectController::class)->defaults('to', '/account');
Route::get('/account/professions', LegacyRedirectController::class)->defaults('to', '/professions')->defaults('with', ['scope' => 'mine']);
Route::get('/account/professions/{slug}', LegacyRedirectController::class)->where('slug', '[a-z-]+')->defaults('to', '/professions/{slug}')->defaults('with', ['scope' => 'mine']);
Route::get('/account/camps', LegacyRedirectController::class)->defaults('to', '/camps')->defaults('with', ['scope' => 'mine']);
Route::get('/account/map', LegacyRedirectController::class)->defaults('to', '/map')->defaults('with', ['scope' => 'mine']);
Route::get('/account/map/trails', LegacyRedirectController::class)->defaults('to', '/map/trails')->defaults('with', ['scope' => 'mine']);

Route::middleware(['auth', 'throttle:60,1'])->group(function (): void {
    Route::get('/account', AccountController::class);
    Route::get('/account/settings', AccountSettingsController::class);
    Route::get('/link', LinkShowController::class);
    Route::post('/link', LinkConfirmController::class)->middleware('throttle:20,1,link-confirm');
});
