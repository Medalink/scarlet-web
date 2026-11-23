<?php

namespace App\Jobs;

use App\Actions\Feats\StoreClassStandings;
use App\Contracts\BoundedJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Store the figures one character's class page compares with its class. Identified by the character, so the feat recounts of one upload queue it once. It runs on the backfill queue, after the decodes of the ingest queue. */
class RefreshClassStandings implements BoundedJob, ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout;

    public int $tries;

    public int $memoryMb;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $characterId)
    {
        $this->timeout = (int) config('scarlet.standings.timeout');
        $this->tries = (int) config('scarlet.standings.tries');
        $this->memoryMb = (int) config('scarlet.standings.memory_mb');
        $this->onQueue((string) config('scarlet.queues.backfill'));
    }

    public function uniqueId(): string
    {
        return 'class-standings:'.$this->characterId;
    }

    public function handle(StoreClassStandings $store): void
    {
        $store->handle($this->characterId);
    }
}
