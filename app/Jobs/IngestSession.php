<?php

namespace App\Jobs;

use App\Actions\Ingest\DecodeSession;
use App\Contracts\BoundedJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

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

    public bool $backfill = false;

    public function __construct(public readonly int $characterId, public readonly int $sessionStart, bool $backfill = false)
    {
        $this->backfill = $backfill;
        $this->timeout = (int) config('scarlet.ingest.timeout');
        $this->tries = (int) config('scarlet.ingest.tries');
        $this->memoryMb = (int) config('scarlet.ingest.memory_mb');
        $this->onQueue((string) config($this->backfill ? 'scarlet.queues.backfill' : 'scarlet.queues.ingest'));
    }

    public function uniqueId(): string
    {
        return ($this->backfill ? 'backfill:' : '').$this->characterId.':'.$this->sessionStart;
    }

    /** One decode of a session at a time, on any host: a live upload and a backfill of the same session wait for each other. */
    public function handle(DecodeSession $decode): void
    {
        Cache::lock($this->decodeLockKey(), $this->timeout + 30)
            ->block(120, fn () => $decode->handle($this->characterId, $this->sessionStart));
    }

    private function decodeLockKey(): string
    {
        return 'decode:'.$this->characterId.':'.$this->sessionStart;
    }
}
