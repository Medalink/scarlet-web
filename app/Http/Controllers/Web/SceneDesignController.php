<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Scene;
use App\Support\SceneArt;
use App\View\Design\Gallery;
use App\View\Design\SceneHead;
use Illuminate\Contracts\View\View;

/** A scene's head over invented facts, in the local environment only (routes/web.php). */
class SceneDesignController extends Controller
{
    public function __invoke(Scene $scene): View
    {
        return view('design.scene', ['d' => SceneHead::data($scene), 'art' => SceneArt::stage($scene), 'base' => Gallery::BASE]);
    }
}
