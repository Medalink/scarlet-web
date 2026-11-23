<?php

namespace App\Http\Controllers\Web;

use App\Actions\Release\ShowReleaseNotes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ReleaseNotesRequest;
use App\View\Pages\ReleaseNotesPage;
use Illuminate\Contracts\View\View;

/** The release notes: every published version, and the build that is running. Public. */
class ReleaseNotesController extends Controller
{
    public function __invoke(ReleaseNotesRequest $request, ShowReleaseNotes $releases): View
    {
        return view('pages.release-notes', ['d' => ReleaseNotesPage::data($releases->handle(), $request->version(), $request->kind())]);
    }
}
