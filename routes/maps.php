<?php

use App\Http\Controllers\Web\CampArtController;
use App\Http\Controllers\Web\DungeonMapController;
use App\Http\Controllers\Web\MapTileController;
use Illuminate\Support\Facades\Route;

Route::get('/map-tiles/manifest.json', [MapTileController::class, 'manifest'])->name('map-tiles.manifest');

Route::get('/map-tiles/{version}/{look}/{z}/{x}/{y}.webp', [MapTileController::class, 'tile'])
    ->where(['version' => '[0-9a-f]{10}', 'z' => '[0-9]{1,2}', 'x' => '[0-9]{1,4}', 'y' => '[0-9]{1,4}'])
    ->name('map-tiles.tile');

Route::get('/dungeon-maps/{instance}/{floor}.webp', [DungeonMapController::class, 'floor'])
    ->where(['instance' => '[0-9]{1,6}', 'floor' => '[0-9]{1,2}'])
    ->name('dungeon-maps.floor');

Route::get('/camp-art/camp.json', [CampArtController::class, 'models'])->name('camp-art.models');
Route::get('/camp-art/tex/{id}.png', [CampArtController::class, 'texture'])->where('id', '[0-9]{1,10}')->name('camp-art.texture');
Route::get('/camp-art/icons/{spell}.png', [CampArtController::class, 'icon'])->where('spell', '[0-9]{1,10}')->name('camp-art.icon');
