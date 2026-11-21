<?php

namespace App\Support;

use App\Models\Install;
use App\Models\UploadUsage;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/** The bytes an install may upload per UTC day (docs/DESIGN.md, Security: Limits). */
class UploadQuota
{
    public function limit(): int
    {
        return (int) config('scarlet.upload.max_bytes_per_install_per_day');
    }

    public function today(): string
    {
        return now()->utc()->toDateString();
    }

    public function used(Install $install): int
    {
        return (int) UploadUsage::query()->where('install_id', $install->id)->where('day', $this->today())->value('bytes');
    }

    public function assertRoom(Install $install, int $bytes): void
    {
        $used = $this->used($install);
        if ($used + $bytes > $this->limit()) {
            throw ApiException::dailyLimit($this->limit(), $used);
        }
    }

    public function record(Install $install, int $bytes): void
    {
        UploadUsage::query()->upsert(
            [['install_id' => $install->id, 'day' => $this->today(), 'bytes' => $bytes, 'requests' => 1]],
            ['install_id', 'day'],
            ['bytes' => DB::raw('upload_usage.bytes + excluded.bytes'), 'requests' => DB::raw('upload_usage.requests + 1')],
        );
    }
}
