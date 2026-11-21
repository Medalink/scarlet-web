<?php

namespace App\Jobs;

use App\Actions\Rulesets\DetectDueRulesets;
use App\Contracts\BoundedJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Ask the Forever Armory for the ruleset of every character that is due. Unique, so the Armory is asked by one
 * request at a time; it stops short of its timeout and the next run takes the rest.
 */
class DetectRulesets implements BoundedJob, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout;

    public int $tries = 1;

    public int $memoryMb = 256;

    public int $uniqueFor = 900;

    public function __construct()
    {
        $this->timeout = (int) config('scarlet.armory.job_seconds') + 40;
        $this->onQueue((string) config('scarlet.queues.armory'));
    }

    public function handle(DetectDueRulesets $detect): void
    {
        $detect->handle((int) config('scarlet.armory.job_seconds'));
    }
}
