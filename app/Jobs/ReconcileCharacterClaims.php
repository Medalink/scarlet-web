<?php

namespace App\Jobs;

use App\Actions\Ingest\ReconcileAddonClaims;
use App\Contracts\BoundedJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Decide again what the addon claims of one character count (docs/DESIGN.md, Reconcile). Identified by the character,
 * so a burst of decodes queues it once; the identity is released when the job starts, so a decode that finishes while
 * it runs queues it again.
 */
class ReconcileCharacterClaims implements BoundedJob, ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout;

    public int $tries;

    public int $memoryMb;

    public int $uniqueFor = 900;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly int $characterId, public readonly bool $backfill = false)
    {
        $this->timeout = (int) config('scarlet.ingest.timeout');
        $this->tries = (int) config('scarlet.ingest.tries');
        $this->memoryMb = (int) config('scarlet.ingest.memory_mb');
        $this->onQueue((string) config($this->backfill ? 'scarlet.queues.backfill' : 'scarlet.queues.ingest'));
    }

    public function uniqueId(): string
    {
        return 'reconcile:'.($this->backfill ? 'backfill:' : '').$this->characterId;
    }

    public function handle(ReconcileAddonClaims $reconcile): void
    {
        $reconcile->handle($this->characterId);
    }
}
