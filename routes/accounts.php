<?php

use App\Http\Controllers\Web\AccountApiKeysController;
use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\AccountPrivacyController;
use App\Http\Controllers\Web\AccountStorageController;
use App\Http\Controllers\Web\AccountUploadHistoryController;
use App\Http\Controllers\Web\AuthCallbackController;
use App\Http\Controllers\Web\AuthDevelopmentAgeUploadsController;
use App\Http\Controllers\Web\AuthDevelopmentController;
use App\Http\Controllers\Web\AuthDevelopmentDropBattlenetController;
use App\Http\Controllers\Web\AuthDevelopmentExpireKeysController;
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
    Route::post('/auth/development/expire-keys', AuthDevelopmentExpireKeysController::class)->middleware(['auth', 'throttle:120,1']);
    Route::post('/auth/development/drop-battlenet', AuthDevelopmentDropBattlenetController::class)->middleware(['auth', 'throttle:120,1']);
    Route::post('/auth/development/age-uploads', AuthDevelopmentAgeUploadsController::class)->middleware(['auth', 'throttle:120,1']);
}

Route::get('/account/characters', LegacyRedirectController::class)->defaults('to', '/account');
Route::get('/account/settings', LegacyRedirectController::class)->defaults('to', '/account');
Route::get('/account/professions', LegacyRedirectController::class)->defaults('to', '/professions')->defaults('with', ['scope' => 'mine']);
Route::get('/account/professions/{slug}', LegacyRedirectController::class)->where('slug', '[a-z-]+')->defaults('to', '/professions/{slug}')->defaults('with', ['scope' => 'mine']);
Route::get('/account/camps', LegacyRedirectController::class)->defaults('to', '/camps')->defaults('with', ['scope' => 'mine']);
Route::get('/account/map', LegacyRedirectController::class)->defaults('to', '/map')->defaults('with', ['scope' => 'mine']);
Route::get('/account/map/trails', LegacyRedirectController::class)->defaults('to', '/map')->defaults('with', ['scope' => 'mine']);

Route::middleware(['auth', 'throttle:60,1'])->group(function (): void {
    Route::get('/account', AccountController::class);
    Route::get('/account/privacy', AccountPrivacyController::class);
    Route::get('/account/api-keys', AccountApiKeysController::class);
    Route::get('/account/storage', AccountStorageController::class);
    Route::get('/account/storage/history', AccountUploadHistoryController::class);
    Route::get('/link', LinkShowController::class);
    Route::post('/link', LinkConfirmController::class)->middleware('throttle:20,1,link-confirm');
});
