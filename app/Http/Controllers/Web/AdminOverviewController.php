<?php

namespace App\Http\Controllers\Web;

use App\Actions\Admin\AdminOverview;
use App\Http\Controllers\Controller;
use App\View\Pages\Admin\OverviewPage;
use Illuminate\Contracts\View\View;

/** GET /admin, the totals and trends of the whole site. AdminOnly lets only an admin account reach it. */
class AdminOverviewController extends Controller
{
    public function __invoke(AdminOverview $numbers): View
    {
        return view('admin.overview', ['d' => OverviewPage::data($numbers->handle())]);
    }
}
