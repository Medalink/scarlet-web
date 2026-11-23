<?php

namespace App\Support;

use App\Models\Install;
use App\Models\UploadUsage;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * The work an install may upload per UTC day (docs/DESIGN.md, Security: Limits): the quota
 * counts the inflated bytes of the stored parts plus one byte per row their `lines` create -
 * the units the decode actually costs, not the compressed bytes on the wire.
 */
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

    public function assertRoom(Install $install, int $bytes, int $rows): void
    {
        $used = $this->used($install);
        if ($used + $this->charge($bytes, $rows) > $this->limit()) {
            throw ApiException::dailyLimit($this->limit(), $used);
        }
    }

    public function record(Install $install, int $bytes, int $rows): void
    {
        UploadUsage::query()->upsert(
            [['install_id' => $install->id, 'day' => $this->today(), 'bytes' => $this->charge($bytes, $rows), 'requests' => 1]],
            ['install_id', 'day'],
            ['bytes' => DB::raw('upload_usage.bytes + excluded.bytes'), 'requests' => DB::raw('upload_usage.requests + 1')],
        );
    }

    private function charge(int $bytes, int $rows): int
    {
        return $bytes + $rows;
    }
}
