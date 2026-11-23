<?php

namespace App\Http\Controllers\Web;

use App\Actions\Release\ShowAppDownload;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** What Scarlet is and the download of the desktop app: the newest installer, the addon zip alone and the releases page. Public. */
class DownloadController extends Controller
{
    public function __invoke(ShowAppDownload $download): View
    {
        return view('pages.pitch', ['app' => $download->handle()]);
    }
}
