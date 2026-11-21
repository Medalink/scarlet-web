<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MapRect;
use Illuminate\Contracts\View\View;

/** The map viewer on its own, in the local environment only (routes/maps.php). */
class MapViewerDesignController extends Controller
{
    public function __invoke(): View
    {
        $zones = MapRect::query()->get(['ui_map_id', 'name', 'u0', 'v0', 'u1', 'v1'])->map(fn (MapRect $rect): array => [
            'id' => $rect->ui_map_id,
            'name' => $rect->name,
            'u0' => $rect->u0,
            'v0' => $rect->v0,
            'u1' => $rect->u1,
            'v1' => $rect->v1,
        ]);

        return view('design.map-viewer', ['zones' => $zones]);
    }
}
