<?php

namespace App\Support;

use App\Models\Install;
use App\Models\UploadUsage;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * The work an install may upload per UTC day (docs/DESIGN.md, Security: Limits): the quota
 * counts the inflated bytes of the stored parts plus one byte per row their `lines` create plus
 * `part_row_charge` a part - the units the decode and the storage actually cost, not the compressed bytes on the wire.
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

    public function room(Install $install): int
    {
        return max(0, $this->limit() - $this->used($install));
    }

    /** What each stored part costs on top of its bytes and lines: a part row is work even when its text is a header. */
    public function partCharge(): int
    {
        return (int) config('scarlet.upload.part_row_charge');
    }

    public function assertRoom(Install $install, int $bytes, int $rows, int $parts = 0): void
    {
        $used = $this->used($install);
        if ($used + $this->charge($bytes, $rows, $parts) > $this->limit()) {
            throw ApiException::dailyLimit($this->limit(), $used);
        }
    }

    public function record(Install $install, int $bytes, int $rows, int $parts = 0): void
    {
        UploadUsage::query()->upsert(
            [['install_id' => $install->id, 'day' => $this->today(), 'bytes' => $this->charge($bytes, $rows, $parts), 'requests' => 1]],
            ['install_id', 'day'],
            ['bytes' => DB::raw('upload_usage.bytes + excluded.bytes'), 'requests' => DB::raw('upload_usage.requests + 1')],
        );
    }

    private function charge(int $bytes, int $rows, int $parts): int
    {
        return $bytes + $rows + $parts * $this->partCharge();
    }
}
