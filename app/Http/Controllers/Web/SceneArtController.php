<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\ItemIcons;
use App\Support\Scene;
use App\Support\SceneArt;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves the game art that tools/scene-art wrote to the `maps` disk: a scene's, and the item icons. Public art, so nothing to authorize. */
class SceneArtController extends Controller
{
    private const string ART_CACHE = 'public, max-age=31536000, immutable';

    private const string MODELS_CACHE = 'public, max-age=300';

    public function models(Scene $scene): StreamedResponse
    {
        return $this->send(SceneArt::models($scene), 'application/json', self::MODELS_CACHE);
    }

    /** The page's addresses carry `?v=` with the version of the art, so one address always holds the same bytes. */
    public function texture(Scene $scene, int $id): StreamedResponse
    {
        return $this->send(SceneArt::path($scene, "tex/{$id}.png"), 'image/png', self::ART_CACHE);
    }

    public function icon(Scene $scene, int $id): StreamedResponse
    {
        return $this->send(SceneArt::path($scene, "icons/{$id}.png"), 'image/png', self::ART_CACHE);
    }

    /** The icon file `tools/scene-art/items.mjs` wrote, named by the file id that `ItemIcons` puts in the address. */
    public function itemIcon(int $id): StreamedResponse
    {
        return $this->send(ItemIcons::path("icons/{$id}.png"), 'image/png', self::ART_CACHE);
    }

    private function send(string $path, string $type, string $cache): StreamedResponse
    {
        $disk = Storage::disk('maps');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, headers: ['Content-Type' => $type, 'Cache-Control' => $cache]);
    }
}
