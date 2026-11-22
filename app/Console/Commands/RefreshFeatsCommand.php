<?php

namespace App\Console\Commands;

use App\Actions\Feats\QueueRefresh;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('feats:refresh {--character=}')]
#[Description('Queues a feat recount for one character, or for every character that has a stored session')]
class RefreshFeatsCommand extends Command
{
    public function handle(QueueRefresh $queue): int
    {
        $character = $this->option('character');

        $this->info($queue->handle($character === null ? null : (int) $character).' characters queued.');

        return self::SUCCESS;
    }
}
