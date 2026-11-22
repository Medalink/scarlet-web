<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use JsonException;

/**
 * One folder of game icons on the `maps` disk (tools/scene-art/items.mjs and spells.mjs): `<folder>.json`, which maps an id
 * to its icon file id, and `icons/<file id>.png`, one per file. A page asks for the icons of its rows in one cache read; each
 * answer, `0` for an id with no icon, is kept for `CACHE_SECONDS`, so a view reads the map, 30 thousand entries, from the
 * disk only while the answers for its ids have expired. A map that cannot be read, as it is while `bin/deploy maps` is
 * writing it, gives a page with no icons and is not cached, so the next page drawn reads it again.
 */
final readonly class IconFolder
{
    private const int CACHE_SECONDS = 300;

    /**
     * @param  string  $folder  the folder on the disk, which also names its map: `items` holds `items/items.json`
     * @param  string  $route  the first segment of the icon addresses and the prefix of the cache keys
     */
    public function __construct(private string $folder, private string $route) {}

    public function path(string $inside): string
    {
        return $this->folder.'/'.$inside;
    }

    /**
     * Each address carries `?v=`, the time the map was written, so one address always holds the same bytes.
     *
     * @param  array<int, int>  $ids
     * @return array<int, string|null> the address of each id's icon, null when the art or the id has none
     */
    public function icons(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        $prefix = $this->route.':';
        $versionKey = $prefix.'version';
        $keys = array_map(fn (int $id): string => $prefix.$id, $ids);
        /** @var array<string, int|false|null> $found */
        $found = Cache::many([$versionKey, ...$keys]);
        $version = $found[$versionKey] ?? null;
        if ($version === null) {
            $version = $this->version();
            Cache::put($versionKey, $version, self::CACHE_SECONDS);
        }
        if ($version === false) {
            return array_fill_keys($ids, null);
        }

        $missing = array_filter($ids, fn (int $id): bool => ! isset($found[$prefix.$id]));
        if ($missing !== []) {
            $map = $this->map();
            if ($map === null) {
                return array_fill_keys($ids, null);
            }
            $fresh = [];
            foreach ($missing as $id) {
                $fresh[$prefix.$id] = $found[$prefix.$id] = $map[$id] ?? 0;
            }
            Cache::putMany($fresh, self::CACHE_SECONDS);
        }

        $icons = [];
        foreach ($ids as $id) {
            $file = $found[$prefix.$id];
            $icons[$id] = $file > 0 ? "/{$this->route}/{$file}.png?v={$version}" : null;
        }

        return $icons;
    }

    private function mapPath(): string
    {
        return $this->path($this->folder.'.json');
    }

    private function version(): int|false
    {
        $disk = Storage::disk('maps');

        return $disk->exists($this->mapPath()) ? $disk->lastModified($this->mapPath()) : false;
    }

    /** @return array<int, int>|null */
    private function map(): ?array
    {
        try {
            $map = json_decode(Storage::disk('maps')->get($this->mapPath()) ?? '', true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($map) ? $map : null;
    }
}
