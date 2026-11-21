<?php

use App\Http\Controllers\Web\MapTileController;
use Illuminate\Support\Facades\Route;

Route::get('/map-tiles/manifest.json', [MapTileController::class, 'manifest'])->name('map-tiles.manifest');

Route::get('/map-tiles/{version}/{look}/{z}/{x}/{y}.webp', [MapTileController::class, 'tile'])
    ->where(['version' => '[0-9a-f]{10}', 'z' => '[0-9]{1,2}', 'x' => '[0-9]{1,4}', 'y' => '[0-9]{1,4}'])
    ->name('map-tiles.tile');
