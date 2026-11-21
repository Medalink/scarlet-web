<?php

use App\Http\Controllers\Api\V1\Upload\InstallController;
use App\Http\Controllers\Api\V1\Upload\UploadController;
use App\Http\Middleware\RateLimitRequests;
use Illuminate\Support\Facades\Route;

// The upload API, versioned by path (docs/API.md). Everything but registration is signed by an install.
Route::prefix('v1')->group(function (): void {
    Route::post('installs', [InstallController::class, 'store'])
        ->middleware('scarlet.rate:'.RateLimitRequests::REGISTRATION);

    Route::middleware(['scarlet.rate:'.RateLimitRequests::IP, 'scarlet.signed', 'scarlet.rate:'.RateLimitRequests::INSTALL])->group(function (): void {
        Route::get('installs/me', [InstallController::class, 'show']);
        Route::post('installs/link-code', [InstallController::class, 'linkCode']);

        Route::prefix('upload')->group(function (): void {
            Route::post('sync', [UploadController::class, 'sync']);
            Route::post('manifest', [UploadController::class, 'manifest']);
            Route::post('parts', [UploadController::class, 'parts']);
            Route::get('status', [UploadController::class, 'status']);
            Route::put('groups/{key}', [UploadController::class, 'restoreGroup'])->where('key', '[a-z]{2,16}');
            Route::delete('groups/{key}', [UploadController::class, 'removeGroup'])->where('key', '[a-z]{2,16}');
            Route::delete('/', [UploadController::class, 'removeEverything']);
        });
    });
});
