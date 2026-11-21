<?php

namespace App\Jobs;

use App\Actions\Rollups\RefreshAggregates;
use App\Contracts\BoundedJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Refresh the continuous aggregates over a time range. The range is widened to whole days, and a job for the
 * same days waits a few seconds, so the ingests of one upload share one refresh.
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
        $this->timeout = (int) config('scarlet.rollups.timeout');
        $this->tries = (int) config('scarlet.rollups.tries');
        $this->memoryMb = (int) config('scarlet.rollups.memory_mb');
        $this->onQueue((string) config('scarlet.queues.rollups'));
        $this->delay((int) config('scarlet.rollups.debounce_seconds'));
    }

    public function uniqueId(): string
    {
        return $this->from.'-'.$this->until;
    }

    public function handle(RefreshAggregates $refresh): void
    {
        $refresh->handle($this->from, $this->until);
    }
}
