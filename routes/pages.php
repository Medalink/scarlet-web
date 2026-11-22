<?php

use App\Http\Controllers\Web\CharacterController;
use App\Http\Controllers\Web\CharacterHeatController;
use App\Http\Controllers\Web\DevelopersController;
use App\Http\Controllers\Web\DownloadController;
use App\Http\Controllers\Web\LeaderboardController;
use App\Http\Controllers\Web\MapController;
use App\Http\Controllers\Web\RecordController;
use App\Http\Controllers\Web\ReleaseNotesController;
use App\Http\Controllers\Web\RulesetsController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\StatsController;
use App\Http\Controllers\Web\TalentsController;
use Illuminate\Support\Facades\Route;

// The public pages: dashboards, the map, boards, search, result pages and characters. Every one reads App\Actions\Public.
Route::get('/stats', StatsController::class);
Route::get('/map', MapController::class);
Route::get('/search', SearchController::class);
Route::get('/developers', DevelopersController::class);
Route::get('/release-notes', ReleaseNotesController::class);
Route::get('/download', DownloadController::class);
Route::get('/c/{name}', CharacterController::class)->where('name', '[^/]+');
Route::get('/c/{name}/heat', CharacterHeatController::class)->where('name', '[^/]+');
Route::get('/creatures/{id}', [RecordController::class, 'creature'])->whereNumber('id');
Route::get('/items/{id}', [RecordController::class, 'item'])->whereNumber('id');
Route::get('/zones/{id}', [RecordController::class, 'zone'])->where('id', '-?[0-9]+');
Route::get('/quests/{id}', [RecordController::class, 'quest'])->whereNumber('id');
Route::get('/leaderboards', [LeaderboardController::class, 'index']);
Route::get('/leaderboards/{board}', [LeaderboardController::class, 'show'])->where('board', '[a-z0-9-]+');
Route::get('/talents', [TalentsController::class, 'index']);
Route::get('/talents/{class}/{name}', [TalentsController::class, 'show'])->where(['class' => '[a-z]+', 'name' => '[a-z0-9-]+']);
Route::get('/rulesets', RulesetsController::class);
Route::get('/rulesets/{slug}', StatsController::class)->where('slug', '[a-z0-9-]+');
