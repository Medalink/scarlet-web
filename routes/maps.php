<?php

use App\Http\Controllers\Web\DungeonMapController;
use App\Http\Controllers\Web\MapTileController;
use App\Http\Controllers\Web\SceneArtController;
use Illuminate\Support\Facades\Route;

Route::get('/map-tiles/manifest.json', [MapTileController::class, 'manifest'])->name('map-tiles.manifest');

Route::get('/map-tiles/{version}/{look}/{z}/{x}/{y}.webp', [MapTileController::class, 'tile'])
    ->where(['version' => '[0-9a-f]{10}', 'z' => '[0-9]{1,2}', 'x' => '[0-9]{1,4}', 'y' => '[0-9]{1,4}'])
    ->name('map-tiles.tile');

Route::get('/dungeon-maps/{instance}/{floor}.webp', [DungeonMapController::class, 'floor'])
    ->where(['instance' => '[0-9]{1,6}', 'floor' => '[0-9]{1,2}'])
    ->name('dungeon-maps.floor');

Route::get('/scene-art/{scene}/models.json', [SceneArtController::class, 'models'])->name('scene-art.models');
Route::get('/scene-art/{scene}/tex/{id}.png', [SceneArtController::class, 'texture'])->where('id', '[0-9]{1,10}')->name('scene-art.texture');
Route::get('/scene-art/{scene}/icons/{id}.png', [SceneArtController::class, 'icon'])->where('id', '[0-9]{1,10}')->name('scene-art.icon');
Route::get('/item-icons/{id}.png', [SceneArtController::class, 'itemIcon'])->where('id', '[0-9]{1,10}')->name('item-icons.icon');
