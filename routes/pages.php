<?php

use App\Http\Controllers\Web\MapController;
use App\Http\Controllers\Web\RulesetsController;
use App\Http\Controllers\Web\StatsController;
use Illuminate\Support\Facades\Route;

// The public pages: dashboards, the map, boards, search, result pages and characters. Every one reads App\Actions\Public.
Route::get('/stats', StatsController::class);
Route::get('/map', MapController::class);
Route::get('/rulesets', RulesetsController::class);
Route::get('/rulesets/{slug}', StatsController::class)->where('slug', '[a-z0-9-]+');
