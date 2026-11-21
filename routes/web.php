<?php

use App\Http\Controllers\Web\DesignController;
use App\View\Design\Gallery;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing');
Route::view('/demo', 'demo');

// The component gallery rebuilds every comp from invented data; it is not part of the site.
if (app()->environment('local')) {
    Route::get('/design', [DesignController::class, 'index']);
    Route::get('/design/{page}', [DesignController::class, 'show'])->where('page', implode('|', array_keys(Gallery::PAGES)));
}

require __DIR__.'/maps.php';
