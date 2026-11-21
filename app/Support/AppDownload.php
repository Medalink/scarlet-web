<?php

namespace App\Support;

/**
 * The newest downloadable build of the Scarlet desktop app, picked from the GitHub releases of the app, and the cache
 * keys that hold it. Every release so far is a pre-release, so GitHub's "latest release" address cannot be used.
 */
final class AppDownload
{
    public const string REPO = 'Medalink/scarlet-app';

    /** The release that only carries the beta update feed. It has no installer. */
    private const string FEED_TAG = 'beta-channel';

    public static function freshKey(): string
    {
        return 'app-download:fresh';
    }

    public static function lastGoodKey(): string
    {
        return 'app-download:last-good';
    }

    public static function waitKey(): string
    {
        return 'app-download:wait';
    }

    public static function releasesUrl(): string
    {
        return 'https://github.com/'.self::REPO.'/releases';
    }

    /**
     * What the pages show when GitHub has not been reachable yet: the releases page and no files.
     *
     * @return array{version: string|null, prerelease: bool, date: string|null, installer: string|null, installer_size: int|null, zip: string|null, sums: string|null, releases: string}
     */
    public static function fallback(): array
    {
        return ['version' => null, 'prerelease' => false, 'date' => null, 'installer' => null, 'installer_size' => null, 'zip' => null, 'sums' => null, 'releases' => self::releasesUrl()];
    }

    /**
     * The newest release that has an installer: the newest stable one when there is any, else the newest
     * pre-release. GitHub lists releases newest first. Drafts and the feed release are skipped.
     *
     * @param  array<mixed>  $releases  the decoded `GET /repos/{repo}/releases`
     * @return array{version: string|null, prerelease: bool, date: string|null, installer: string|null, installer_size: int|null, zip: string|null, sums: string|null, releases: string}|null
     */
    public static function newest(array $releases): ?array
    {
        $best = null;
        foreach ($releases as $release) {
            if (! is_array($release) || ($release['draft'] ?? false) === true || ($release['tag_name'] ?? null) === self::FEED_TAG) {
                continue;
            }
            $found = self::describe($release);
            if ($found === null) {
                continue;
            }
            if (! $found['prerelease']) {
                return $found;
            }
            $best ??= $found;
        }

        return $best;
    }

    /**
     * @param  array<mixed>  $release
     * @return array{version: string|null, prerelease: bool, date: string|null, installer: string|null, installer_size: int|null, zip: string|null, sums: string|null, releases: string}|null
     */
    private static function describe(array $release): ?array
    {
        $tag = $release['tag_name'] ?? null;
        $assets = $release['assets'] ?? null;
        if (! is_string($tag) || ! is_array($assets)) {
            return null;
        }
        $found = ['installer' => null, 'zip' => null, 'sums' => null];
        $size = null;
        foreach ($assets as $asset) {
            $name = is_array($asset) ? ($asset['name'] ?? null) : null;
            $url = is_array($asset) ? ($asset['browser_download_url'] ?? null) : null;
            if (! is_string($name) || ! is_string($url) || ! str_starts_with($url, 'https://github.com/'.self::REPO.'/releases/download/')) {
                continue;
            }
            if (preg_match('/^Scarlet_.+_x64-setup\.exe$/', $name) === 1) {
                $found['installer'] = $url;
                $size = is_int($asset['size'] ?? null) ? $asset['size'] : null;
            }
            if (preg_match('/^Scarlet-.+\.zip$/', $name) === 1) {
                $found['zip'] = $url;
            }
            if ($name === 'SHA256SUMS.txt') {
                $found['sums'] = $url;
            }
        }
        if ($found['installer'] === null) {
            return null;
        }
        $published = $release['published_at'] ?? null;

        return [
            'version' => ltrim($tag, 'v'),
            'prerelease' => ($release['prerelease'] ?? false) === true,
            'date' => is_string($published) ? $published : null,
            'installer' => $found['installer'],
            'installer_size' => $size,
            'zip' => $found['zip'],
            'sums' => $found['sums'],
            'releases' => self::releasesUrl(),
        ];
    }
}
