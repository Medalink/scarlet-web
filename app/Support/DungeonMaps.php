<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * The dungeon floor maps on the `maps` disk under `dungeons/` (tools/map-tiles/dungeons.mjs): `dungeons.json` and
 * `<instance map id>/<floor>.webp`. The list is read once in `CACHE_SECONDS`, so a page view makes no storage call.
 */
final class DungeonMaps
{
    public const string FOLDER = 'dungeons';

    private const int CACHE_SECONDS = 300;

    private const string CACHE_KEY = 'dungeon-maps:state';

    /**
     * The floors of an instance, first floor first, or none while the maps are not on the disk or have none for it.
     * `src` carries the version of the build, so one address always holds the same bytes. A boss's x and y are shares
     * of the picture, across and down.
     *
     * @return list<array{name: string|null, credit: string|null, src: string, width: int, height: int, bosses: list<array{name: string, x: float, y: float}>}>
     */
    public static function floors(int $instance): array
    {
        $state = self::state();
        $floors = [];
        foreach ($state['maps'][(string) $instance]['floors'] ?? [] as $n => $floor) {
            $floors[] = [
                'name' => isset($floor['name']) ? (string) $floor['name'] : null,
                'credit' => isset($floor['credit']) ? (string) $floor['credit'] : null,
                'src' => '/dungeon-maps/'.$instance.'/'.($n + 1).'.webp?v='.$state['version'],
                'width' => (int) $state['width'],
                'height' => (int) $state['height'],
                'bosses' => array_values(array_map(
                    fn (array $boss): array => ['name' => (string) $boss['name'], 'x' => (float) $boss['x'], 'y' => (float) $boss['y']],
                    $floor['bosses'] ?? [],
                )),
            ];
        }

        return $floors;
    }

    public static function path(string $inside): string
    {
        return self::FOLDER.'/'.$inside;
    }

    /** @return array<string, mixed> the list `dungeons.json` holds, with no maps while it is not on the disk */
    private static function state(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function (): array {
            $disk = Storage::disk('maps');
            $list = $disk->exists(self::path('dungeons.json')) ? json_decode((string) $disk->get(self::path('dungeons.json')), true) : null;

            return is_array($list) && isset($list['version'], $list['width'], $list['height'], $list['maps']) ? $list : ['maps' => []];
        });
    }
}
