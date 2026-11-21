<?php

namespace App\Actions\Release;

use App\Support\AppDownload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ShowAppDownload
{
    /**
     * The newest build of the Scarlet desktop app and its files, read from the GitHub releases of the app. The answer
     * is cached for `scarlet.app_download.fresh_seconds`, so page views never reach GitHub. When GitHub does not
     * answer, the last good answer is shown, and before there is one the link to the releases page.
     *
     * @return array{version: string|null, prerelease: bool, date: string|null, installer: string|null, installer_size: int|null, zip: string|null, sums: string|null, releases: string}
     */
    public function handle(): array
    {
        /** @var array{version: string|null, prerelease: bool, date: string|null, installer: string|null, installer_size: int|null, zip: string|null, sums: string|null, releases: string}|null $fresh */
        $fresh = Cache::get(AppDownload::freshKey());
        if ($fresh !== null) {
            return $fresh;
        }
        if (! Cache::has(AppDownload::waitKey())) {
            $found = $this->fetch();
            if ($found !== null) {
                Cache::put(AppDownload::freshKey(), $found, (int) config('scarlet.app_download.fresh_seconds'));
                Cache::put(AppDownload::lastGoodKey(), $found, now()->addDays(30));

                return $found;
            }
            Cache::put(AppDownload::waitKey(), true, (int) config('scarlet.app_download.retry_seconds'));
        }

        /** @var array{version: string|null, prerelease: bool, date: string|null, installer: string|null, installer_size: int|null, zip: string|null, sums: string|null, releases: string}|null $lastGood */
        $lastGood = Cache::get(AppDownload::lastGoodKey());

        return $lastGood ?? AppDownload::fallback();
    }

    /** @return array{version: string|null, prerelease: bool, date: string|null, installer: string|null, installer_size: int|null, zip: string|null, sums: string|null, releases: string}|null */
    private function fetch(): ?array
    {
        try {
            $response = Http::acceptJson()
                ->withUserAgent('scarletweb.io')
                ->timeout((int) config('scarlet.app_download.timeout_seconds'))
                ->get('https://api.github.com/repos/'.AppDownload::REPO.'/releases', ['per_page' => 30]);
        } catch (Throwable) {
            return null;
        }
        if (! $response->successful()) {
            return null;
        }
        $releases = $response->json();

        return is_array($releases) ? AppDownload::newest($releases) : null;
    }
}
