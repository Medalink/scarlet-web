<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\HardcoreDeaths;
use App\Http\Controllers\Controller;
use App\View\Pages\HardcoreDeathsPage;
use Illuminate\Contracts\View\View;

/** Hardcore deaths by level and by day, each death counted once. Public. */
class HardcoreDeathsController extends Controller
{
    public function __invoke(HardcoreDeaths $deaths): View
    {
        return view('pages.hardcore-deaths', ['d' => HardcoreDeathsPage::data($deaths->handle())]);
    }
}
