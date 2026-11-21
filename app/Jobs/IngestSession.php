<?php

namespace App\Jobs;

use App\Actions\Ingest\DecodeSession;
use App\Contracts\BoundedJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Decode one session of one character into rows. Identified by the pair, so a burst of uploads queues it
 * once; the identity is released when the job starts, so a part that arrives while it runs queues it again.
 */
class IngestSession implements BoundedJob, ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout;

    public int $tries;

    public int $memoryMb;

    public int $uniqueFor = 900;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly int $characterId, public readonly int $sessionStart)
    {
        $this->timeout = (int) config('scarlet.ingest.timeout');
        $this->tries = (int) config('scarlet.ingest.tries');
        $this->memoryMb = (int) config('scarlet.ingest.memory_mb');
        $this->onQueue((string) config('scarlet.queues.ingest'));
    }

    public function uniqueId(): string
    {
        return $this->characterId.':'.$this->sessionStart;
    }

    public function handle(DecodeSession $decode): void
    {
        $decode->handle($this->characterId, $this->sessionStart);
    }
}
