<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\MapLook;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves the world map tiles that tools/map-tiles wrote to the `maps` disk. Public art, so nothing to authorize. */
class MapTileController extends Controller
{
    private const string TILE_CACHE = 'public, max-age=31536000, immutable';

    private const string MANIFEST_CACHE = 'public, max-age=300';

    /** 204 when no art is installed: browsers log a 404 as a console error, and this is a normal state. */
    public function manifest(): Response
    {
        $disk = Storage::disk('maps');
        if (! $disk->exists('manifest.json')) {
            return response()->noContent();
        }

        return response((string) $disk->get('manifest.json'), headers: [
            'Content-Type' => 'application/json',
            'Cache-Control' => self::MANIFEST_CACHE,
        ]);
    }

    /** The version is part of the path, so one URL always holds the same bytes and may be cached for good. */
    public function tile(string $version, MapLook $look, int $z, int $x, int $y): StreamedResponse
    {
        $disk = Storage::disk('maps');
        $path = "{$version}/{$look->value}/{$z}/{$x}/{$y}.webp";
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, headers: [
            'Content-Type' => 'image/webp',
            'Cache-Control' => self::TILE_CACHE,
        ]);
    }
}
