<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * The camp page's game art on the `maps` disk under `camp/` (tools/camp-art): `camp.json`, `tex/<file id>.png` and
 * `icons/<spell id>.png`. The page asks which of it exists, once in `CACHE_SECONDS`, so a page view makes no storage call.
 */
final class CampArt
{
    public const string FOLDER = 'camp';

    private const int CACHE_SECONDS = 300;

    private const string CACHE_KEY = 'camp-art:state';

    /**
     * Null while the art is not on the disk. `version` changes whenever `camp.json` is written, and is part of every art
     * address the page builds, so a cached address always holds the same bytes.
     *
     * @return array{version: int, icons: list<int>}|null
     */
    public static function state(): ?array
    {
        /** @var array{version: int, icons: list<int>}|false $state */
        $state = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function (): array|false {
            $disk = Storage::disk('maps');
            if (! $disk->exists(self::path('camp.json'))) {
                return false;
            }

            return [
                'version' => $disk->lastModified(self::path('camp.json')),
                'icons' => array_values(array_filter(array_map(
                    fn (string $file): int => (int) pathinfo($file, PATHINFO_FILENAME),
                    $disk->files(self::path('icons')),
                ))),
            ];
        });

        return $state === false ? null : $state;
    }

    public static function path(string $inside): string
    {
        return self::FOLDER.'/'.$inside;
    }

    /** The address of a benefit's icon, or null when the art has none for it. */
    public static function icon(int $spellId): ?string
    {
        $state = self::state();

        return $state !== null && in_array($spellId, $state['icons'], true) ? "/camp-art/icons/{$spellId}.png?v={$state['version']}" : null;
    }
}
