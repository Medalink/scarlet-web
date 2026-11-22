<?php

namespace App\Console\Commands;

use App\Actions\Ingest\QueueUndecodedSessions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ingest:sweep')]
#[Description('Queues the sessions that hold stored parts nobody decoded, such as an ingest job lost before it ran')]
class SweepUndecodedPartsCommand extends Command
{
    public function handle(QueueUndecodedSessions $sweep): int
    {
        $this->info($sweep->handle().' undecoded sessions found.');

        return self::SUCCESS;
    }
}
