<?php

namespace App\Jobs;

use App\Actions\Rollups\RefreshAggregates;
use App\Contracts\BoundedJob;
use App\Support\PendingRefresh;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Refresh the continuous aggregates over a time range. The range is widened to whole days and added to the days
 * waiting in `PendingRefresh`. Only one of these jobs waits at a time, a few seconds, and it refreshes everything
 * pending when it runs, so the ingests of an upload, or of many uploads, share one refresh.
 * A refresh elsewhere can hold the rollup lock for minutes, longer than the wait in `RefreshAggregates`: such a run
 * puts its range back and goes back on the queue instead of failing, and only runs out of retries when the lock
 * stays busy for every one of the `scarlet.rollups.tries` tries.
 */
class RefreshRollups implements BoundedJob, ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    private const int DAY = 86400;

    /** How long a run whose wait for the rollup lock timed out stays off the queue before it tries again. */
    private const int RETRY_SECONDS = 60;

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
        } catch (LockTimeoutException $busy) {
            PendingRefresh::add($from, $until);
            $this->release(self::RETRY_SECONDS);

            return;
        } catch (Throwable $failure) {
            PendingRefresh::add($from, $until);

            throw $failure;
        }
    }
}
