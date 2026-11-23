<?php

namespace App\Console\Commands;

use App\Actions\Rollups\RebuildKeyedLoot;
use App\Support\RollupLock;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;

#[Signature('loot:rebuild-keyed')]
#[Description('Rebuilds the five keyed loot tables and the group share receipts for every day, under the lock the aggregate refreshes hold')]
class RebuildKeyedLootCommand extends Command
{
    private const int WAIT_SECONDS = 600;

    public function handle(RebuildKeyedLoot $rebuild): int
    {
        $started = microtime(true);
        try {
            RollupLock::block(self::WAIT_SECONDS, fn () => $rebuild->handle());
        } catch (LockTimeoutException) {
            $this->error('The rollup lock was still held after '.self::WAIT_SECONDS.' s; nothing was rebuilt.');

            return self::FAILURE;
        }
        $this->line('rebuilt every day in '.round(microtime(true) - $started, 1).' s');

        return self::SUCCESS;
    }
}
