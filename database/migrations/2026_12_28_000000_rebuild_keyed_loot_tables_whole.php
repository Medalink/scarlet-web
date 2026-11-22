<?php

use App\Actions\Rollups\RebuildKeyedLoot;
use App\Support\RollupLock;
use Illuminate\Database\Migrations\Migration;

/**
 * Rebuilds the five keyed loot tables for every day once. Every refresh now rebuilds only the days it covers, so the days
 * counted under the earlier rules (credit-filtered kills, peer windows that repeat an own read, creature skins read as
 * loot) are corrected here and nowhere else. It waits for a refresh that is running on the lock the refreshes share, and
 * it runs outside the migrator's transaction so the rebuild commits before the lock is released.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    private const int WAIT_SECONDS = 120;

    public function up(): void
    {
        RollupLock::block(self::WAIT_SECONDS, fn () => app(RebuildKeyedLoot::class)->handle());
    }
};
