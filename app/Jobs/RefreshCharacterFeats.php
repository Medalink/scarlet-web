<?php

namespace App\Jobs;

use App\Actions\Feats\StoreCharacterFeats;
use App\Contracts\BoundedJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Count and store the feats of one character. Identified by the character, so the decodes of one
 * upload queue it once; it counts from the base tables, so it reads the decode of its own session.
 */
class RefreshCharacterFeats implements BoundedJob, ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout;

    public int $tries;

    public int $memoryMb;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $characterId)
    {
        $this->timeout = (int) config('scarlet.feats.timeout');
        $this->tries = (int) config('scarlet.feats.tries');
        $this->memoryMb = (int) config('scarlet.feats.memory_mb');
        $this->onQueue((string) config('scarlet.queues.ingest'));
    }

    public function uniqueId(): string
    {
        return 'feats:'.$this->characterId;
    }

    public function handle(StoreCharacterFeats $store): void
    {
        $store->handle($this->characterId);
    }
}
