<?php

namespace App\Http\Controllers\Web;

use App\Actions\Admin\ListCharacters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\AdminCharactersRequest;
use App\View\Pages\Admin\CharactersPage;
use Illuminate\Contracts\View\View;

/** GET /admin/characters, every character, public and private, with a search. AdminOnly lets only an admin account reach it. */
class AdminCharactersController extends Controller
{
    public function __invoke(AdminCharactersRequest $request, ListCharacters $characters): View
    {
        $search = $request->search();
        $visibility = $request->visibility();

        return view('admin.characters', ['d' => CharactersPage::data($characters->handle($search, $visibility, $request->page()), $search, $visibility)]);
    }
}
