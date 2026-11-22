<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * A scene's game art on the `maps` disk under its own folder (tools/scene-art): `<scene>.json`, `tex/<file id>.png` and
 * `icons/<id>.png`. The page asks which of it exists, once in `CACHE_SECONDS`, so a page view makes no storage call.
 */
final class SceneArt
{
    private const int CACHE_SECONDS = 300;

    private const string CACHE_KEY = 'scene-art:state:';

    /**
     * Null while the art is not on the disk. `version` changes whenever the models file is written, and is part of every
     * art address the page builds, so a cached address always holds the same bytes.
     *
     * @return array{version: int, icons: list<int>}|null
     */
    public static function state(Scene $scene): ?array
    {
        /** @var array{version: int, icons: list<int>}|false $state */
        $state = Cache::remember(self::CACHE_KEY.$scene->value, self::CACHE_SECONDS, function () use ($scene): array|false {
            $disk = Storage::disk('maps');
            if (! $disk->exists(self::models($scene))) {
                return false;
            }

            return [
                'version' => $disk->lastModified(self::models($scene)),
                'icons' => array_values(array_filter(array_map(
                    fn (string $file): int => (int) pathinfo($file, PATHINFO_FILENAME),
                    $disk->files(self::path($scene, 'icons')),
                ))),
            ];
        });

        return $state === false ? null : $state;
    }

    public static function path(Scene $scene, string $inside): string
    {
        return $scene->value.'/'.$inside;
    }

    public static function models(Scene $scene): string
    {
        return self::path($scene, $scene->value.'.json');
    }

    /**
     * What the page's stage element carries: where the models are and the template of a texture's address.
     *
     * @return array{scene: string, models: string, texture: string}|null
     */
    public static function stage(Scene $scene): ?array
    {
        $state = self::state($scene);
        if ($state === null) {
            return null;
        }
        $base = "/scene-art/{$scene->value}";

        return ['scene' => $scene->value, 'models' => "{$base}/models.json?v={$state['version']}", 'texture' => "{$base}/tex/{id}.png?v={$state['version']}"];
    }

    /** The address of an icon, or null when the art has none for that id. */
    public static function icon(Scene $scene, int $id): ?string
    {
        $state = self::state($scene);

        return $state !== null && in_array($id, $state['icons'], true) ? "/scene-art/{$scene->value}/icons/{$id}.png?v={$state['version']}" : null;
    }
}
