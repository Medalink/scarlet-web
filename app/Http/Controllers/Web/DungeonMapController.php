<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\DungeonMaps;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves the dungeon floor maps that tools/map-tiles/dungeons.mjs wrote to the `maps` disk. Public art, so nothing to authorize. */
class DungeonMapController extends Controller
{
    /** The page's addresses carry `?v=` with the version of the build, so one address always holds the same bytes. */
    public function floor(int $instance, int $floor): StreamedResponse
    {
        $disk = Storage::disk('maps');
        $path = DungeonMaps::path("{$instance}/{$floor}.webp");
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, headers: ['Content-Type' => 'image/webp', 'Cache-Control' => 'public, max-age=31536000, immutable']);
    }
}
