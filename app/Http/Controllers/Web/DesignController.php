<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\View\Design\Gallery;
use Illuminate\Contracts\View\View as ViewContract;

/** The component gallery, local only: each comp rebuilt from invented data through the layout and components. */
class DesignController extends Controller
{
    public function index(): ViewContract
    {
        return view('design.index', ['pages' => array_keys(Gallery::PAGES), 'days' => Gallery::dayHeat()]);
    }

    public function show(string $page): ViewContract
    {
        return view(Gallery::VIEWS[$page], ['d' => Gallery::data($page)]);
    }
}
