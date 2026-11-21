<?php

namespace App\Console\Commands;

use App\Actions\Ingest\QueueRedecode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ingest:redecode')]
#[Description('Decodes again every stored session, so rows written by an older decoder follow the current one')]
class RedecodeSessionsCommand extends Command
{
    public function handle(QueueRedecode $redecode): int
    {
        $this->info($redecode->handle().' sessions queued.');

        return self::SUCCESS;
    }
}
