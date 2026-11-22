<?php

use App\Http\Controllers\Web\DesignController;
use App\Http\Controllers\Web\MapViewerDesignController;
use App\Http\Controllers\Web\SceneDesignController;
use App\Http\Middleware\RateLimitRequests;
use App\View\Design\Gallery;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->middleware('scarlet.edge');
Route::view('/demo', 'demo')->middleware('scarlet.edge');

// The component gallery rebuilds every comp from invented data; it is not part of the site.
if (app()->environment('local')) {
    Route::get('/design', [DesignController::class, 'index']);
    Route::get('/design/map-viewer', MapViewerDesignController::class);
    Route::get('/design/scenes/{scene}', SceneDesignController::class);
    Route::get('/design/{page}', [DesignController::class, 'show'])->where('page', implode('|', array_keys(Gallery::PAGES)));
}

require __DIR__.'/maps.php';
require __DIR__.'/accounts.php';
Route::middleware(['scarlet.rate:'.RateLimitRequests::PAGE, 'scarlet.timeout', 'scarlet.edge'])->group(__DIR__.'/pages.php');
