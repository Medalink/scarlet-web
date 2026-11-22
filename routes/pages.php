<?php

use App\Http\Controllers\Web\CampsController;
use App\Http\Controllers\Web\CharacterBattlegroundsController;
use App\Http\Controllers\Web\CharacterController;
use App\Http\Controllers\Web\CharacterHeatController;
use App\Http\Controllers\Web\DevelopersController;
use App\Http\Controllers\Web\DownloadController;
use App\Http\Controllers\Web\HardcoreDeathsController;
use App\Http\Controllers\Web\LeaderboardController;
use App\Http\Controllers\Web\LootController;
use App\Http\Controllers\Web\MapController;
use App\Http\Controllers\Web\ProfessionController;
use App\Http\Controllers\Web\RecordController;
use App\Http\Controllers\Web\ReleaseNotesController;
use App\Http\Controllers\Web\RulesetsController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\SearchSuggestController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Controllers\Web\StatsController;
use App\Http\Controllers\Web\TalentsController;
use App\Support\SearchKind;
use Illuminate\Support\Facades\Route;

// The public pages: dashboards, the map, boards, search, result pages and characters. Every one reads App\Actions\Public.
Route::get('/stats', StatsController::class);
Route::get('/map', MapController::class);
Route::get('/loot', LootController::class);
Route::get('/camps', CampsController::class);
Route::get('/professions/{slug}', ProfessionController::class)->where('slug', '[a-z-]+');
Route::get('/search', SearchController::class);
Route::get('/search/suggest', SearchSuggestController::class);
Route::get('/developers', DevelopersController::class);
Route::get('/release-notes', ReleaseNotesController::class);
Route::get('/download', DownloadController::class);
Route::get('/c/{name}', CharacterController::class)->where('name', '[^/]+');
Route::get('/c/{name}/battlegrounds', CharacterBattlegroundsController::class)->where('name', '[^/]+');
Route::get('/c/{name}/heat', CharacterHeatController::class)->where('name', '[^/]+');
Route::get('/creatures/{id}', [RecordController::class, 'creature'])->whereNumber('id');
Route::get('/objects/{id}', [RecordController::class, 'object'])->whereNumber('id');
Route::get('/items/{id}', [RecordController::class, 'item'])->whereNumber('id');
Route::get('/spells/{id}', [RecordController::class, 'spell'])->whereNumber('id');
Route::get('/encounters/{id}', [RecordController::class, 'encounter'])->whereNumber('id');
Route::get('/zones/{id}', [RecordController::class, 'zone'])->where('id', '-?[0-9]+');
Route::get('/quests/{id}', [RecordController::class, 'quest'])->whereNumber('id');
Route::get('/leaderboards', [LeaderboardController::class, 'index']);
Route::get('/leaderboards/{board}', [LeaderboardController::class, 'show'])->where('board', '[a-z0-9-]+');
Route::get('/talents', [TalentsController::class, 'index']);
Route::get('/talents/{class}/{name}', [TalentsController::class, 'show'])->where(['class' => '[a-z]+', 'name' => '[a-z0-9-]+']);
Route::get('/hardcore-deaths', HardcoreDeathsController::class);
Route::get('/rulesets', RulesetsController::class);
Route::get('/rulesets/{slug}', StatsController::class)->where('slug', '[a-z0-9-]+');
Route::get('/sitemap.xml', [SitemapController::class, 'index']);
Route::get('/sitemaps/pages.xml', [SitemapController::class, 'pages']);
Route::get('/sitemaps/{kind}-{page}.xml', [SitemapController::class, 'kind'])->where([
    'kind' => implode('|', array_map(fn (SearchKind $kind): string => $kind->value, SearchKind::cases())),
    'page' => '[1-9][0-9]{0,3}',
]);
