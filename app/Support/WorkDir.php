<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The ingest work dir (`scarlet.work_dir`), where decodes write their rows and spool files. A job killed by its
 * timeout or a memory fatal never runs its `finally`, so each host removes decode and imports work files older than
 * an hour, at most once every ten minutes, when it makes a new one.
 */
final class WorkDir
{
    private const int STALE_SECONDS = 3600;

    private const int SWEEP_EVERY_SECONDS = 600;

    private const array PREFIXES = ['scarlet-decode-', 'scarlet-imports-'];

    /** A new work file, `<prefix><unique>.ndjson`; `$prefix` is one of the swept prefixes. */
    public static function file(string $prefix): string
    {
        $dir = rtrim((string) config('scarlet.work_dir'), '/\\');
        self::sweep($dir);

        return $dir.DIRECTORY_SEPARATOR.$prefix.uniqid().'.ndjson';
    }

    private static function sweep(string $dir): void
    {
        if (! Cache::add(self::sweepKey(), true, self::SWEEP_EVERY_SECONDS)) {
            return;
        }
        $cutoff = time() - self::STALE_SECONDS;
        foreach (self::PREFIXES as $prefix) {
            foreach (glob($dir.DIRECTORY_SEPARATOR.$prefix.'*') ?: [] as $path) {
                $modified = is_file($path) ? filemtime($path) : false;
                if ($modified !== false && $modified < $cutoff) {
                    unlink($path);
                }
            }
        }
    }

    private static function sweepKey(): string
    {
        return 'work-dir-sweep:'.gethostname();
    }
}
