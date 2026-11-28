<?php

use App\Http\Controllers\Web\CampsController;
use App\Http\Controllers\Web\CharacterPageController;
use App\Http\Controllers\Web\CharacterSectionRedirectController;
use App\Http\Controllers\Web\CharacterSessionController;
use App\Http\Controllers\Web\DevelopersController;
use App\Http\Controllers\Web\DownloadController;
use App\Http\Controllers\Web\FeatController;
use App\Http\Controllers\Web\FeatsController;
use App\Http\Controllers\Web\HardcoreDeathsController;
use App\Http\Controllers\Web\LeaderboardController;
use App\Http\Controllers\Web\LegacyRedirectController;
use App\Http\Controllers\Web\LootController;
use App\Http\Controllers\Web\MapController;
use App\Http\Controllers\Web\MapTrailsController;
use App\Http\Controllers\Web\ProfessionController;
use App\Http\Controllers\Web\ProfessionsController;
use App\Http\Controllers\Web\RecordController;
use App\Http\Controllers\Web\RulesetsController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\SearchSuggestController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Controllers\Web\StatsController;
use App\Http\Controllers\Web\TalentsController;
use App\Support\PlayerClass;
use App\Support\RecordId;
use App\Support\SearchKind;
use Illuminate\Support\Facades\Route;

// The public pages: dashboards, the map, boards, search, result pages and characters. Every one reads App\Actions\Public.
Route::get('/stats', StatsController::class);
Route::get('/stats/compare', RulesetsController::class);
Route::get('/stats/{slug}', StatsController::class)->where('slug', '[a-z0-9-]+');
Route::get('/map', MapController::class);
Route::get('/loot', LootController::class);
Route::get('/loot/groups', LegacyRedirectController::class)->defaults('to', '/loot')->defaults('with', ['view' => 'groups']);
Route::get('/camps', CampsController::class);
Route::get('/professions', ProfessionsController::class);
Route::get('/professions/{slug}', ProfessionController::class)->where('slug', '[a-z-]+');
Route::get('/search', SearchController::class);
Route::get('/search/suggest', SearchSuggestController::class);
Route::get('/developers', DevelopersController::class);
Route::get('/release-notes', LegacyRedirectController::class)->defaults('to', '/download')->defaults('anchor', 'release-notes');
Route::get('/download', DownloadController::class);
Route::get('/feats', FeatsController::class);
Route::get('/feats/{key}', FeatController::class)->where('key', '[a-z-]+');
Route::get('/c/{name}', CharacterPageController::class)->where('name', '[^/]+');
Route::get('/c/{name}/sessions/{start}', CharacterSessionController::class)->where(['name' => '[^/]+', 'start' => '[0-9]+']);
Route::get('/c/{name}/battlegrounds', CharacterSectionRedirectController::class)->where('name', '[^/]+')->defaults('anchor', 'battlegrounds');
Route::get('/c/{name}/{class}', CharacterSectionRedirectController::class)->where(['name' => '[^/]+', 'class' => implode('|', array_map(fn (PlayerClass $class): string => $class->slug(), PlayerClass::cases()))])->defaults('anchor', 'class');
Route::get('/c/{name}/heat', LegacyRedirectController::class)->where('name', '[^/]+')->defaults('to', '/map')->defaults('with', ['character' => '{name}']);
Route::get('/c/{name}/trails', LegacyRedirectController::class)->where('name', '[^/]+')->defaults('to', '/map')->defaults('with', ['character' => '{name}']);
Route::get('/map/trails', MapTrailsController::class);
Route::get('/creatures/{id}', [RecordController::class, 'creature'])->where('id', RecordId::pattern());
Route::get('/objects/{id}', [RecordController::class, 'object'])->where('id', RecordId::pattern());
Route::get('/items/{id}', [RecordController::class, 'item'])->where('id', RecordId::pattern());
Route::get('/spells/{id}', [RecordController::class, 'spell'])->where('id', RecordId::pattern());
Route::get('/encounters/{id}', [RecordController::class, 'encounter'])->where('id', RecordId::pattern());
Route::get('/zones/{id}', [RecordController::class, 'zone'])->where('id', '-?'.RecordId::pattern());
Route::get('/quests/{id}', [RecordController::class, 'quest'])->where('id', RecordId::pattern());
Route::get('/leaderboards', [LeaderboardController::class, 'index']);
Route::get('/leaderboards/{board}', [LeaderboardController::class, 'show'])->where('board', '[a-z0-9-]+');
Route::get('/talents', [TalentsController::class, 'index']);
Route::get('/talents/{class}/{name}', [TalentsController::class, 'show'])->where(['class' => '[a-z]+', 'name' => '[a-z0-9-]+']);
Route::get('/hardcore-deaths', HardcoreDeathsController::class);
Route::get('/rulesets', LegacyRedirectController::class)->defaults('to', '/stats/compare');
Route::get('/rulesets/{slug}', LegacyRedirectController::class)->where('slug', '[a-z0-9-]+')->defaults('to', '/stats/{slug}');
Route::get('/sitemap.xml', [SitemapController::class, 'index']);
Route::get('/sitemaps/pages.xml', [SitemapController::class, 'pages']);
Route::get('/sitemaps/{kind}-{page}.xml', [SitemapController::class, 'kind'])->where([
    'kind' => implode('|', array_map(fn (SearchKind $kind): string => $kind->value, SearchKind::cases())),
    'page' => '[1-9][0-9]{0,3}',
]);
