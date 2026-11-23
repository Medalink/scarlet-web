<?php

namespace App\Http\Controllers\Web;

use App\Actions\Admin\ApiLimits;
use App\Http\Controllers\Controller;
use App\View\Pages\Admin\LimitsPage;
use Illuminate\Contracts\View\View;

/** GET /admin/limits, who reaches the limits, the busiest callers today and uploads against their caps. AdminOnly lets only an admin account reach it. */
class AdminLimitsController extends Controller
{
    public function __invoke(ApiLimits $numbers): View
    {
        return view('admin.limits', ['d' => LimitsPage::data($numbers->handle())]);
    }
}
