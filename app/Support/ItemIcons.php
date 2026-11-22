<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use JsonException;

/**
 * The item icons on the `maps` disk under `items/` (tools/scene-art/items.mjs): `items.json`, which maps an item id to
 * its icon file id, and `icons/<file id>.png`, one per file. A page asks for the icons of its rows in one cache read; each
 * answer, `0` for an item with no icon, is kept for `CACHE_SECONDS`, so a view reads the 30 thousand entry map from the
 * disk only while the answers for its ids have expired. A map that cannot be read, as it is while `bin/deploy maps` is
 * writing it, gives a page with no icons and is not cached, so the next page drawn reads it again.
 */
final class ItemIcons
{
    private const int CACHE_SECONDS = 300;

    private const string CACHE_KEY = 'item-icons:';

    private const string VERSION_KEY = self::CACHE_KEY.'version';

    private const string FOLDER = 'items';

    private const string MAP = 'items.json';

    public static function path(string $inside): string
    {
        return self::FOLDER.'/'.$inside;
    }

    /**
     * Each address carries `?v=`, the time `items.json` was written, so one address always holds the same bytes.
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, string|null> the address of each item's icon, null when the art or the item has none
     */
    public static function icons(array $itemIds): array
    {
        $ids = array_values(array_unique($itemIds));
        $keys = array_map(fn (int $id): string => self::CACHE_KEY.$id, $ids);
        /** @var array<string, int|false|null> $found */
        $found = Cache::many([self::VERSION_KEY, ...$keys]);
        $version = $found[self::VERSION_KEY] ?? null;
        if ($version === null) {
            $version = self::version();
            Cache::put(self::VERSION_KEY, $version, self::CACHE_SECONDS);
        }
        if ($version === false) {
            return array_fill_keys($ids, null);
        }

        $missing = array_filter($ids, fn (int $id): bool => ! isset($found[self::CACHE_KEY.$id]));
        if ($missing !== []) {
            $map = self::map();
            if ($map === null) {
                return array_fill_keys($ids, null);
            }
            $fresh = [];
            foreach ($missing as $id) {
                $fresh[self::CACHE_KEY.$id] = $found[self::CACHE_KEY.$id] = $map[$id] ?? 0;
            }
            Cache::putMany($fresh, self::CACHE_SECONDS);
        }

        $icons = [];
        foreach ($ids as $id) {
            $file = $found[self::CACHE_KEY.$id];
            $icons[$id] = $file > 0 ? "/item-icons/{$file}.png?v={$version}" : null;
        }

        return $icons;
    }

    private static function version(): int|false
    {
        $disk = Storage::disk('maps');

        return $disk->exists(self::path(self::MAP)) ? $disk->lastModified(self::path(self::MAP)) : false;
    }

    /** @return array<int, int>|null */
    private static function map(): ?array
    {
        try {
            $map = json_decode(Storage::disk('maps')->get(self::path(self::MAP)) ?? '', true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($map) ? $map : null;
    }
}
