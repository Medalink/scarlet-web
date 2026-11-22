<?php

use App\Http\Controllers\Web\AdminApiController;
use App\Http\Controllers\Web\AdminDatabaseController;
use App\Http\Controllers\Web\AdminIngestController;
use App\Http\Controllers\Web\AdminOverviewController;
use App\Http\Middleware\AdminOnly;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['throttle:60,1', AdminOnly::class, 'scarlet.timeout'])->group(function (): void {
    Route::get('/', AdminOverviewController::class);
    Route::get('/api', AdminApiController::class);
    Route::get('/database', AdminDatabaseController::class);
    Route::get('/ingest', AdminIngestController::class);
});
