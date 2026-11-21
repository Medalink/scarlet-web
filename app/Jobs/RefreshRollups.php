<?php

namespace App\Jobs;

use App\Actions\Rollups\RefreshAggregates;
use App\Contracts\BoundedJob;
use App\Support\PendingRefresh;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Refresh the continuous aggregates over a time range. The range is widened to whole days and added to the days
 * waiting in `PendingRefresh`. Only one of these jobs waits at a time, a few seconds, and it refreshes everything
 * pending when it runs, so the ingests of an upload, or of many uploads, share one refresh.
 */
class RefreshRollups implements BoundedJob, ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    private const int DAY = 86400;

    public int $timeout;

    public int $tries;

    public int $memoryMb;

    public int $uniqueFor = 3600;

    public readonly int $from;

    public readonly int $until;

    public function __construct(int $from, int $until)
    {
        $this->from = intdiv($from, self::DAY) * self::DAY;
        $this->until = (intdiv($until, self::DAY) + 1) * self::DAY;
        PendingRefresh::add($this->from, $this->until);
        $this->timeout = (int) config('scarlet.rollups.timeout');
        $this->tries = (int) config('scarlet.rollups.tries');
        $this->memoryMb = (int) config('scarlet.rollups.memory_mb');
        $this->onQueue((string) config('scarlet.queues.rollups'));
        $this->delay((int) config('scarlet.rollups.debounce_seconds'));
    }

    public function uniqueId(): string
    {
        return 'rollups';
    }

    public function handle(RefreshAggregates $refresh): void
    {
        $pending = PendingRefresh::take();
        $from = min($this->from, $pending['from'] ?? $this->from);
        $until = max($this->until, $pending['until'] ?? $this->until);

        try {
            $refresh->handle($from, $until);
        } catch (Throwable $failure) {
            PendingRefresh::add($from, $until);

            throw $failure;
        }
    }
}
